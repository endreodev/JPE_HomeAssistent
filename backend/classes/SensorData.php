<?php
/**
 * Classe SensorData - Gerenciamento de dados de sensores
 */

require_once '../config/database.php';

class SensorData {
    private $conn;
    private $table = 'sensor_data';

    public $id;
    public $device_id;
    public $sensor_type;
    public $sensor_value;
    public $unit;
    public $timestamp;
    public $metadata;

    public function __construct($db) {
        $this->conn = $db;
    }

    /**
     * Salvar dados do sensor
     */
    public function save() {
        $query = "INSERT INTO " . $this->table . " 
                  SET device_id = :device_id,
                      sensor_type = :sensor_type,
                      sensor_value = :sensor_value,
                      unit = :unit,
                      metadata = :metadata";

        $stmt = $this->conn->prepare($query);

        // Limpar dados
        $this->sensor_type = htmlspecialchars(strip_tags($this->sensor_type));
        $this->unit = htmlspecialchars(strip_tags($this->unit ?: ''));
        
        // Converter metadata para JSON se necessário
        if (is_array($this->metadata)) {
            $this->metadata = json_encode($this->metadata);
        }

        // Bind dos parâmetros
        $stmt->bindParam(':device_id', $this->device_id);
        $stmt->bindParam(':sensor_type', $this->sensor_type);
        $stmt->bindParam(':sensor_value', $this->sensor_value);
        $stmt->bindParam(':unit', $this->unit);
        $stmt->bindParam(':metadata', $this->metadata);

        if ($stmt->execute()) {
            $this->id = $this->conn->lastInsertId();
            return true;
        }

        return false;
    }

    /**
     * Buscar dados por dispositivo
     */
    public function findByDeviceId($device_id, $sensor_type = null, $limit = 100, $from_date = null, $to_date = null) {
        $query = "SELECT sd.*, d.user_id 
                  FROM " . $this->table . " sd
                  INNER JOIN devices d ON sd.device_id = d.id
                  WHERE sd.device_id = :device_id";
        
        if ($sensor_type) {
            $query .= " AND sd.sensor_type = :sensor_type";
        }
        
        if ($from_date) {
            $query .= " AND sd.timestamp >= :from_date";
        }
        
        if ($to_date) {
            $query .= " AND sd.timestamp <= :to_date";
        }
        
        $query .= " ORDER BY sd.timestamp DESC LIMIT :limit";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':device_id', $device_id);
        
        if ($sensor_type) {
            $stmt->bindParam(':sensor_type', $sensor_type);
        }
        
        if ($from_date) {
            $stmt->bindParam(':from_date', $from_date);
        }
        
        if ($to_date) {
            $stmt->bindParam(':to_date', $to_date);
        }
        
        $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Buscar último valor de um sensor
     */
    public function getLatestValue($device_id, $sensor_type, $user_id = null) {
        $query = "SELECT sd.* 
                  FROM " . $this->table . " sd
                  INNER JOIN devices d ON sd.device_id = d.id
                  WHERE sd.device_id = :device_id 
                  AND sd.sensor_type = :sensor_type";
        
        if ($user_id) {
            $query .= " AND d.user_id = :user_id";
        }
        
        $query .= " ORDER BY sd.timestamp DESC LIMIT 1";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':device_id', $device_id);
        $stmt->bindParam(':sensor_type', $sensor_type);
        
        if ($user_id) {
            $stmt->bindParam(':user_id', $user_id);
        }
        
        $stmt->execute();

        if ($stmt->rowCount() > 0) {
            return $stmt->fetch(PDO::FETCH_ASSOC);
        }

        return null;
    }

    /**
     * Buscar agregações de dados
     */
    public function getAggregatedData($device_id, $sensor_type, $interval = 'hour', $from_date = null, $to_date = null) {
        // Definir formato de agrupamento baseado no intervalo
        $group_format = match($interval) {
            'minute' => '%Y-%m-%d %H:%i:00',
            'hour' => '%Y-%m-%d %H:00:00',
            'day' => '%Y-%m-%d 00:00:00',
            'month' => '%Y-%m-01 00:00:00',
            default => '%Y-%m-%d %H:00:00'
        };

        $query = "SELECT 
                    DATE_FORMAT(timestamp, '$group_format') as period,
                    AVG(sensor_value) as avg_value,
                    MIN(sensor_value) as min_value,
                    MAX(sensor_value) as max_value,
                    COUNT(*) as count
                  FROM " . $this->table . " 
                  WHERE device_id = :device_id 
                  AND sensor_type = :sensor_type";
        
        if ($from_date) {
            $query .= " AND timestamp >= :from_date";
        }
        
        if ($to_date) {
            $query .= " AND timestamp <= :to_date";
        }
        
        $query .= " GROUP BY period ORDER BY period ASC";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':device_id', $device_id);
        $stmt->bindParam(':sensor_type', $sensor_type);
        
        if ($from_date) {
            $stmt->bindParam(':from_date', $from_date);
        }
        
        if ($to_date) {
            $stmt->bindParam(':to_date', $to_date);
        }
        
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Buscar tipos de sensores disponíveis para um dispositivo
     */
    public function getSensorTypes($device_id) {
        $query = "SELECT DISTINCT sensor_type, unit
                  FROM " . $this->table . " 
                  WHERE device_id = :device_id 
                  ORDER BY sensor_type";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':device_id', $device_id);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Buscar dados por usuário
     */
    public function findByUserId($user_id, $sensor_type = null, $limit = 100) {
        $query = "SELECT sd.*, d.name as device_name 
                  FROM " . $this->table . " sd
                  INNER JOIN devices d ON sd.device_id = d.id
                  WHERE d.user_id = :user_id";
        
        if ($sensor_type) {
            $query .= " AND sd.sensor_type = :sensor_type";
        }
        
        $query .= " ORDER BY sd.timestamp DESC LIMIT :limit";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':user_id', $user_id);
        
        if ($sensor_type) {
            $stmt->bindParam(':sensor_type', $sensor_type);
        }
        
        $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Deletar dados antigos
     */
    public function deleteOldData($days = 90) {
        $query = "DELETE FROM " . $this->table . " 
                  WHERE timestamp < DATE_SUB(NOW(), INTERVAL :days DAY)";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':days', $days, PDO::PARAM_INT);

        return $stmt->execute();
    }

    /**
     * Buscar estatísticas do dispositivo
     */
    public function getDeviceStatistics($device_id, $days = 7) {
        $query = "SELECT 
                    sensor_type,
                    COUNT(*) as total_readings,
                    AVG(sensor_value) as avg_value,
                    MIN(sensor_value) as min_value,
                    MAX(sensor_value) as max_value,
                    MIN(timestamp) as first_reading,
                    MAX(timestamp) as last_reading
                  FROM " . $this->table . " 
                  WHERE device_id = :device_id 
                  AND timestamp >= DATE_SUB(NOW(), INTERVAL :days DAY)
                  GROUP BY sensor_type
                  ORDER BY sensor_type";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':device_id', $device_id);
        $stmt->bindParam(':days', $days, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Salvar múltiplos dados de sensores em lote
     */
    public function saveBatch($sensor_data_array) {
        $query = "INSERT INTO " . $this->table . " 
                  (device_id, sensor_type, sensor_value, unit, metadata) 
                  VALUES ";
        
        $values = [];
        $params = [];
        
        foreach ($sensor_data_array as $index => $data) {
            $values[] = "(:device_id_$index, :sensor_type_$index, :sensor_value_$index, :unit_$index, :metadata_$index)";
            $params[":device_id_$index"] = $data['device_id'];
            $params[":sensor_type_$index"] = htmlspecialchars(strip_tags($data['sensor_type']));
            $params[":sensor_value_$index"] = $data['sensor_value'];
            $params[":unit_$index"] = htmlspecialchars(strip_tags($data['unit'] ?? ''));
            $params[":metadata_$index"] = is_array($data['metadata']) ? json_encode($data['metadata']) : $data['metadata'];
        }
        
        $query .= implode(',', $values);
        
        $stmt = $this->conn->prepare($query);
        
        foreach ($params as $param => $value) {
            $stmt->bindValue($param, $value);
        }
        
        return $stmt->execute();
    }

    /**
     * Converter para array
     */
    public function toArray() {
        return [
            'id' => $this->id,
            'device_id' => $this->device_id,
            'sensor_type' => $this->sensor_type,
            'sensor_value' => $this->sensor_value,
            'unit' => $this->unit,
            'timestamp' => $this->timestamp,
            'metadata' => $this->metadata ? json_decode($this->metadata, true) : null
        ];
    }
}
?>