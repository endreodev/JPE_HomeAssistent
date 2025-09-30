<?php
/**
 * Classe DeviceAction - Gerenciamento de ações/comandos para dispositivos
 */

require_once '../config/database.php';

class DeviceAction {
    private $conn;
    private $table = 'device_actions';

    public $id;
    public $device_id;
    public $action_type;
    public $action_data;
    public $status;
    public $created_at;
    public $executed_at;
    public $response_data;
    public $error_message;

    public function __construct($db) {
        $this->conn = $db;
    }

    /**
     * Criar nova ação
     */
    public function create() {
        $query = "INSERT INTO " . $this->table . " 
                  SET device_id = :device_id,
                      action_type = :action_type,
                      action_data = :action_data,
                      status = :status";

        $stmt = $this->conn->prepare($query);

        // Limpar dados
        $this->action_type = htmlspecialchars(strip_tags($this->action_type));
        $this->status = htmlspecialchars(strip_tags($this->status ?: 'pending'));
        
        // Converter action_data para JSON se necessário
        if (is_array($this->action_data)) {
            $this->action_data = json_encode($this->action_data);
        }

        // Bind dos parâmetros
        $stmt->bindParam(':device_id', $this->device_id);
        $stmt->bindParam(':action_type', $this->action_type);
        $stmt->bindParam(':action_data', $this->action_data);
        $stmt->bindParam(':status', $this->status);

        if ($stmt->execute()) {
            $this->id = $this->conn->lastInsertId();
            return true;
        }

        return false;
    }

    /**
     * Buscar ações por dispositivo
     */
    public function findByDeviceId($device_id, $limit = 50) {
        $query = "SELECT da.*, d.user_id 
                  FROM " . $this->table . " da
                  INNER JOIN devices d ON da.device_id = d.id
                  WHERE da.device_id = :device_id 
                  ORDER BY da.created_at DESC 
                  LIMIT :limit";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':device_id', $device_id);
        $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Buscar ações pendentes do usuário
     */
    public function findPendingByUserId($user_id) {
        $query = "SELECT da.*, d.name as device_name 
                  FROM " . $this->table . " da
                  INNER JOIN devices d ON da.device_id = d.id
                  WHERE d.user_id = :user_id AND da.status = 'pending'
                  ORDER BY da.created_at ASC";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Buscar ação por ID
     */
    public function findById($id, $user_id = null) {
        $query = "SELECT da.*, d.user_id 
                  FROM " . $this->table . " da
                  INNER JOIN devices d ON da.device_id = d.id
                  WHERE da.id = :id";
        
        if ($user_id) {
            $query .= " AND d.user_id = :user_id";
        }

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id);
        
        if ($user_id) {
            $stmt->bindParam(':user_id', $user_id);
        }
        
        $stmt->execute();

        if ($stmt->rowCount() > 0) {
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            $this->populateFromArray($row);
            return true;
        }

        return false;
    }

    /**
     * Atualizar status da ação
     */
    public function updateStatus($status, $response_data = null, $error_message = null) {
        $query = "UPDATE " . $this->table . " 
                  SET status = :status,
                      executed_at = CURRENT_TIMESTAMP";
        
        if ($response_data !== null) {
            $query .= ", response_data = :response_data";
        }
        
        if ($error_message !== null) {
            $query .= ", error_message = :error_message";
        }
        
        $query .= " WHERE id = :id";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':status', $status);
        $stmt->bindParam(':id', $this->id);
        
        if ($response_data !== null) {
            if (is_array($response_data)) {
                $response_data = json_encode($response_data);
            }
            $stmt->bindParam(':response_data', $response_data);
        }
        
        if ($error_message !== null) {
            $stmt->bindParam(':error_message', $error_message);
        }

        return $stmt->execute();
    }

    /**
     * Marcar ação como enviada
     */
    public function markAsSent() {
        return $this->updateStatus('sent');
    }

    /**
     * Marcar ação como completada
     */
    public function markAsCompleted($response_data = null) {
        return $this->updateStatus('completed', $response_data);
    }

    /**
     * Marcar ação como falhada
     */
    public function markAsFailed($error_message) {
        return $this->updateStatus('failed', null, $error_message);
    }

    /**
     * Deletar ação
     */
    public function delete() {
        $query = "DELETE FROM " . $this->table . " WHERE id = :id";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $this->id);

        return $stmt->execute();
    }

    /**
     * Limpar ações antigas (mais de 30 dias)
     */
    public function cleanOldActions($days = 30) {
        $query = "DELETE FROM " . $this->table . " 
                  WHERE created_at < DATE_SUB(NOW(), INTERVAL :days DAY)
                  AND status IN ('completed', 'failed')";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':days', $days, PDO::PARAM_INT);

        return $stmt->execute();
    }

    /**
     * Obter estatísticas de ações
     */
    public function getStatistics($user_id, $days = 7) {
        $query = "SELECT 
                    COUNT(*) as total,
                    SUM(CASE WHEN da.status = 'pending' THEN 1 ELSE 0 END) as pending,
                    SUM(CASE WHEN da.status = 'sent' THEN 1 ELSE 0 END) as sent,
                    SUM(CASE WHEN da.status = 'completed' THEN 1 ELSE 0 END) as completed,
                    SUM(CASE WHEN da.status = 'failed' THEN 1 ELSE 0 END) as failed
                  FROM " . $this->table . " da
                  INNER JOIN devices d ON da.device_id = d.id
                  WHERE d.user_id = :user_id 
                  AND da.created_at >= DATE_SUB(NOW(), INTERVAL :days DAY)";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->bindParam(':days', $days, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Preencher objeto com array
     */
    private function populateFromArray($data) {
        $this->id = $data['id'];
        $this->device_id = $data['device_id'];
        $this->action_type = $data['action_type'];
        $this->action_data = $data['action_data'];
        $this->status = $data['status'];
        $this->created_at = $data['created_at'];
        $this->executed_at = $data['executed_at'];
        $this->response_data = $data['response_data'];
        $this->error_message = $data['error_message'];
    }

    /**
     * Converter para array
     */
    public function toArray() {
        return [
            'id' => $this->id,
            'device_id' => $this->device_id,
            'action_type' => $this->action_type,
            'action_data' => json_decode($this->action_data, true),
            'status' => $this->status,
            'created_at' => $this->created_at,
            'executed_at' => $this->executed_at,
            'response_data' => $this->response_data ? json_decode($this->response_data, true) : null,
            'error_message' => $this->error_message
        ];
    }
}
?>