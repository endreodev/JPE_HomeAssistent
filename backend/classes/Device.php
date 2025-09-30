<?php
/**
 * Classe Device - Gerenciamento de dispositivos IoT
 */

require_once '../config/database.php';

class Device {
    private $conn;
    private $table = 'devices';

    public $id;
    public $user_id;
    public $name;
    public $device_id;
    public $mac_address;
    public $device_type;
    public $service_uuid;
    public $status;
    public $last_ssid;
    public $last_configured;
    public $rssi;
    public $is_connected;
    public $location;
    public $description;
    public $created_at;
    public $updated_at;

    public function __construct($db) {
        $this->conn = $db;
    }

    /**
     * Criar novo dispositivo
     */
    public function create() {
        $query = "INSERT INTO " . $this->table . " 
                  SET user_id = :user_id,
                      name = :name,
                      device_id = :device_id,
                      mac_address = :mac_address,
                      device_type = :device_type,
                      service_uuid = :service_uuid,
                      status = :status,
                      location = :location,
                      description = :description";

        $stmt = $this->conn->prepare($query);

        // Limpar dados
        $this->name = htmlspecialchars(strip_tags($this->name));
        $this->device_id = htmlspecialchars(strip_tags($this->device_id));
        $this->mac_address = htmlspecialchars(strip_tags($this->mac_address));
        $this->device_type = htmlspecialchars(strip_tags($this->device_type ?: 'ESP32'));
        $this->service_uuid = htmlspecialchars(strip_tags($this->service_uuid ?: '12345678-1234-1234-1234-1234567890ab'));
        $this->status = htmlspecialchars(strip_tags($this->status ?: 'not_configured'));
        $this->location = htmlspecialchars(strip_tags($this->location));
        $this->description = htmlspecialchars(strip_tags($this->description));

        // Bind dos parâmetros
        $stmt->bindParam(':user_id', $this->user_id);
        $stmt->bindParam(':name', $this->name);
        $stmt->bindParam(':device_id', $this->device_id);
        $stmt->bindParam(':mac_address', $this->mac_address);
        $stmt->bindParam(':device_type', $this->device_type);
        $stmt->bindParam(':service_uuid', $this->service_uuid);
        $stmt->bindParam(':status', $this->status);
        $stmt->bindParam(':location', $this->location);
        $stmt->bindParam(':description', $this->description);

        if ($stmt->execute()) {
            $this->id = $this->conn->lastInsertId();
            return true;
        }

        return false;
    }

    /**
     * Buscar dispositivos do usuário
     */
    public function findByUserId($user_id) {
        $query = "SELECT * FROM " . $this->table . " 
                  WHERE user_id = :user_id 
                  ORDER BY created_at DESC";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Buscar dispositivo por ID
     */
    public function findById($id, $user_id = null) {
        $query = "SELECT * FROM " . $this->table . " WHERE id = :id";
        
        if ($user_id) {
            $query .= " AND user_id = :user_id";
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
     * Buscar dispositivo por device_id
     */
    public function findByDeviceId($device_id, $user_id) {
        $query = "SELECT * FROM " . $this->table . " 
                  WHERE device_id = :device_id AND user_id = :user_id";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':device_id', $device_id);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->execute();

        if ($stmt->rowCount() > 0) {
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            $this->populateFromArray($row);
            return true;
        }

        return false;
    }

    /**
     * Atualizar dispositivo
     */
    public function update() {
        $query = "UPDATE " . $this->table . " 
                  SET name = :name,
                      device_type = :device_type,
                      status = :status,
                      last_ssid = :last_ssid,
                      last_configured = :last_configured,
                      rssi = :rssi,
                      is_connected = :is_connected,
                      location = :location,
                      description = :description,
                      updated_at = CURRENT_TIMESTAMP
                  WHERE id = :id AND user_id = :user_id";

        $stmt = $this->conn->prepare($query);

        // Limpar dados
        $this->name = htmlspecialchars(strip_tags($this->name));
        $this->device_type = htmlspecialchars(strip_tags($this->device_type));
        $this->status = htmlspecialchars(strip_tags($this->status));
        $this->last_ssid = htmlspecialchars(strip_tags($this->last_ssid));
        $this->location = htmlspecialchars(strip_tags($this->location));
        $this->description = htmlspecialchars(strip_tags($this->description));

        // Bind dos parâmetros
        $stmt->bindParam(':name', $this->name);
        $stmt->bindParam(':device_type', $this->device_type);
        $stmt->bindParam(':status', $this->status);
        $stmt->bindParam(':last_ssid', $this->last_ssid);
        $stmt->bindParam(':last_configured', $this->last_configured);
        $stmt->bindParam(':rssi', $this->rssi);
        $stmt->bindParam(':is_connected', $this->is_connected);
        $stmt->bindParam(':location', $this->location);
        $stmt->bindParam(':description', $this->description);
        $stmt->bindParam(':id', $this->id);
        $stmt->bindParam(':user_id', $this->user_id);

        return $stmt->execute();
    }

    /**
     * Atualizar status do dispositivo
     */
    public function updateStatus($status, $is_connected = null) {
        $query = "UPDATE " . $this->table . " 
                  SET status = :status,
                      updated_at = CURRENT_TIMESTAMP";
        
        if ($is_connected !== null) {
            $query .= ", is_connected = :is_connected";
        }
        
        $query .= " WHERE id = :id";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':status', $status);
        $stmt->bindParam(':id', $this->id);
        
        if ($is_connected !== null) {
            $stmt->bindParam(':is_connected', $is_connected);
        }

        return $stmt->execute();
    }

    /**
     * Atualizar configuração Wi-Fi
     */
    public function updateWifiConfig($ssid, $status = 'configured') {
        $query = "UPDATE " . $this->table . " 
                  SET last_ssid = :ssid,
                      status = :status,
                      last_configured = CURRENT_TIMESTAMP,
                      updated_at = CURRENT_TIMESTAMP
                  WHERE id = :id";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':ssid', $ssid);
        $stmt->bindParam(':status', $status);
        $stmt->bindParam(':id', $this->id);

        return $stmt->execute();
    }

    /**
     * Deletar dispositivo
     */
    public function delete() {
        $query = "DELETE FROM " . $this->table . " 
                  WHERE id = :id AND user_id = :user_id";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $this->id);
        $stmt->bindParam(':user_id', $this->user_id);

        return $stmt->execute();
    }

    /**
     * Obter estatísticas dos dispositivos do usuário
     */
    public function getStatistics($user_id) {
        $query = "SELECT 
                    COUNT(*) as total,
                    SUM(CASE WHEN status = 'configured' THEN 1 ELSE 0 END) as configured,
                    SUM(CASE WHEN status = 'not_configured' THEN 1 ELSE 0 END) as not_configured,
                    SUM(CASE WHEN status = 'online' THEN 1 ELSE 0 END) as online,
                    SUM(CASE WHEN status = 'offline' THEN 1 ELSE 0 END) as offline,
                    SUM(CASE WHEN is_connected = 1 THEN 1 ELSE 0 END) as connected
                  FROM " . $this->table . " 
                  WHERE user_id = :user_id";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->execute();

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Verificar se dispositivo existe para o usuário
     */
    public function deviceExists($device_id, $user_id) {
        $query = "SELECT id FROM " . $this->table . " 
                  WHERE device_id = :device_id AND user_id = :user_id";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':device_id', $device_id);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->execute();

        return $stmt->rowCount() > 0;
    }

    /**
     * Preencher objeto com array
     */
    private function populateFromArray($data) {
        $this->id = $data['id'];
        $this->user_id = $data['user_id'];
        $this->name = $data['name'];
        $this->device_id = $data['device_id'];
        $this->mac_address = $data['mac_address'];
        $this->device_type = $data['device_type'];
        $this->service_uuid = $data['service_uuid'];
        $this->status = $data['status'];
        $this->last_ssid = $data['last_ssid'];
        $this->last_configured = $data['last_configured'];
        $this->rssi = $data['rssi'];
        $this->is_connected = $data['is_connected'];
        $this->location = $data['location'];
        $this->description = $data['description'];
        $this->created_at = $data['created_at'];
        $this->updated_at = $data['updated_at'];
    }

    /**
     * Converter para array
     */
    public function toArray() {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'name' => $this->name,
            'device_id' => $this->device_id,
            'mac_address' => $this->mac_address,
            'device_type' => $this->device_type,
            'service_uuid' => $this->service_uuid,
            'status' => $this->status,
            'last_ssid' => $this->last_ssid,
            'last_configured' => $this->last_configured,
            'rssi' => $this->rssi,
            'is_connected' => (bool)$this->is_connected,
            'location' => $this->location,
            'description' => $this->description,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at
        ];
    }
}
?>