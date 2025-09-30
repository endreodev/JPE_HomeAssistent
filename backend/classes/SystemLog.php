<?php
/**
 * Sistema de Logs - Gerenciamento de logs do sistema
 */

require_once '../config/database.php';

class SystemLog {
    private $conn;
    private $table = 'system_logs';

    public $id;
    public $user_id;
    public $action;
    public $resource_type;
    public $resource_id;
    public $ip_address;
    public $user_agent;
    public $details;
    public $created_at;

    public function __construct($db) {
        $this->conn = $db;
    }

    /**
     * Registrar log
     */
    public function log($user_id, $action, $resource_type = null, $resource_id = null, $details = null) {
        $query = "INSERT INTO " . $this->table . " 
                  SET user_id = :user_id,
                      action = :action,
                      resource_type = :resource_type,
                      resource_id = :resource_id,
                      ip_address = :ip_address,
                      user_agent = :user_agent,
                      details = :details";

        $stmt = $this->conn->prepare($query);

        // Limpar dados
        $action = htmlspecialchars(strip_tags($action));
        $resource_type = $resource_type ? htmlspecialchars(strip_tags($resource_type)) : null;
        
        // Obter IP e User Agent
        $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
        
        // Converter details para JSON se necessário
        if (is_array($details)) {
            $details = json_encode($details);
        }

        // Bind dos parâmetros
        $stmt->bindParam(':user_id', $user_id);
        $stmt->bindParam(':action', $action);
        $stmt->bindParam(':resource_type', $resource_type);
        $stmt->bindParam(':resource_id', $resource_id);
        $stmt->bindParam(':ip_address', $ip_address);
        $stmt->bindParam(':user_agent', $user_agent);
        $stmt->bindParam(':details', $details);

        return $stmt->execute();
    }

    /**
     * Buscar logs por usuário
     */
    public function findByUserId($user_id, $limit = 50, $offset = 0) {
        $query = "SELECT * FROM " . $this->table . " 
                  WHERE user_id = :user_id 
                  ORDER BY created_at DESC 
                  LIMIT :limit OFFSET :offset";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Buscar logs por ação
     */
    public function findByAction($action, $limit = 50) {
        $query = "SELECT sl.*, u.username 
                  FROM " . $this->table . " sl
                  LEFT JOIN users u ON sl.user_id = u.id
                  WHERE sl.action = :action 
                  ORDER BY sl.created_at DESC 
                  LIMIT :limit";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':action', $action);
        $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Buscar logs por recurso
     */
    public function findByResource($resource_type, $resource_id = null, $limit = 50) {
        $query = "SELECT sl.*, u.username 
                  FROM " . $this->table . " sl
                  LEFT JOIN users u ON sl.user_id = u.id
                  WHERE sl.resource_type = :resource_type";
        
        if ($resource_id) {
            $query .= " AND sl.resource_id = :resource_id";
        }
        
        $query .= " ORDER BY sl.created_at DESC LIMIT :limit";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':resource_type', $resource_type);
        
        if ($resource_id) {
            $stmt->bindParam(':resource_id', $resource_id);
        }
        
        $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Buscar logs com filtros
     */
    public function findWithFilters($filters = [], $limit = 50, $offset = 0) {
        $query = "SELECT sl.*, u.username 
                  FROM " . $this->table . " sl
                  LEFT JOIN users u ON sl.user_id = u.id
                  WHERE 1=1";
        
        $params = [];
        
        if (!empty($filters['user_id'])) {
            $query .= " AND sl.user_id = :user_id";
            $params[':user_id'] = $filters['user_id'];
        }
        
        if (!empty($filters['action'])) {
            $query .= " AND sl.action = :action";
            $params[':action'] = $filters['action'];
        }
        
        if (!empty($filters['resource_type'])) {
            $query .= " AND sl.resource_type = :resource_type";
            $params[':resource_type'] = $filters['resource_type'];
        }
        
        if (!empty($filters['ip_address'])) {
            $query .= " AND sl.ip_address = :ip_address";
            $params[':ip_address'] = $filters['ip_address'];
        }
        
        if (!empty($filters['from_date'])) {
            $query .= " AND sl.created_at >= :from_date";
            $params[':from_date'] = $filters['from_date'];
        }
        
        if (!empty($filters['to_date'])) {
            $query .= " AND sl.created_at <= :to_date";
            $params[':to_date'] = $filters['to_date'];
        }
        
        $query .= " ORDER BY sl.created_at DESC LIMIT :limit OFFSET :offset";

        $stmt = $this->conn->prepare($query);
        
        foreach ($params as $param => $value) {
            $stmt->bindValue($param, $value);
        }
        
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Contar logs com filtros
     */
    public function countWithFilters($filters = []) {
        $query = "SELECT COUNT(*) as total FROM " . $this->table . " sl WHERE 1=1";
        
        $params = [];
        
        if (!empty($filters['user_id'])) {
            $query .= " AND sl.user_id = :user_id";
            $params[':user_id'] = $filters['user_id'];
        }
        
        if (!empty($filters['action'])) {
            $query .= " AND sl.action = :action";
            $params[':action'] = $filters['action'];
        }
        
        if (!empty($filters['resource_type'])) {
            $query .= " AND sl.resource_type = :resource_type";
            $params[':resource_type'] = $filters['resource_type'];
        }
        
        if (!empty($filters['ip_address'])) {
            $query .= " AND sl.ip_address = :ip_address";
            $params[':ip_address'] = $filters['ip_address'];
        }
        
        if (!empty($filters['from_date'])) {
            $query .= " AND sl.created_at >= :from_date";
            $params[':from_date'] = $filters['from_date'];
        }
        
        if (!empty($filters['to_date'])) {
            $query .= " AND sl.created_at <= :to_date";
            $params[':to_date'] = $filters['to_date'];
        }

        $stmt = $this->conn->prepare($query);
        
        foreach ($params as $param => $value) {
            $stmt->bindValue($param, $value);
        }
        
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $result['total'];
    }

    /**
     * Obter estatísticas de logs
     */
    public function getStatistics($days = 7) {
        $query = "SELECT 
                    action,
                    COUNT(*) as total,
                    COUNT(DISTINCT user_id) as unique_users,
                    COUNT(DISTINCT ip_address) as unique_ips
                  FROM " . $this->table . " 
                  WHERE created_at >= DATE_SUB(NOW(), INTERVAL :days DAY)
                  GROUP BY action
                  ORDER BY total DESC";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':days', $days, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Deletar logs antigos
     */
    public function deleteOldLogs($days = 365) {
        $query = "DELETE FROM " . $this->table . " 
                  WHERE created_at < DATE_SUB(NOW(), INTERVAL :days DAY)";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':days', $days, PDO::PARAM_INT);

        return $stmt->execute();
    }

    /**
     * Logs de ações específicas
     */
    public static function logLogin($db, $user_id, $success = true) {
        $log = new self($db);
        $action = $success ? 'login_success' : 'login_failed';
        return $log->log($user_id, $action);
    }

    public static function logLogout($db, $user_id) {
        $log = new self($db);
        return $log->log($user_id, 'logout');
    }

    public static function logDeviceAction($db, $user_id, $device_id, $action_type, $details = null) {
        $log = new self($db);
        return $log->log($user_id, 'device_action', 'device', $device_id, [
            'action_type' => $action_type,
            'details' => $details
        ]);
    }

    public static function logDeviceCreate($db, $user_id, $device_id) {
        $log = new self($db);
        return $log->log($user_id, 'device_create', 'device', $device_id);
    }

    public static function logDeviceUpdate($db, $user_id, $device_id) {
        $log = new self($db);
        return $log->log($user_id, 'device_update', 'device', $device_id);
    }

    public static function logDeviceDelete($db, $user_id, $device_id) {
        $log = new self($db);
        return $log->log($user_id, 'device_delete', 'device', $device_id);
    }

    public static function logUserUpdate($db, $user_id) {
        $log = new self($db);
        return $log->log($user_id, 'user_update', 'user', $user_id);
    }

    /**
     * Converter para array
     */
    public function toArray() {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'action' => $this->action,
            'resource_type' => $this->resource_type,
            'resource_id' => $this->resource_id,
            'ip_address' => $this->ip_address,
            'user_agent' => $this->user_agent,
            'details' => $this->details ? json_decode($this->details, true) : null,
            'created_at' => $this->created_at
        ];
    }
}
?>