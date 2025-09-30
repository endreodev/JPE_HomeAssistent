-- =========================================
-- IoT Home Assistant - Estrutura do Banco
-- =========================================

-- CREATE DATABASE IF NOT EXISTS u454452574_ibyt_nivel CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE u454452574_ibyt_nivel;

-- Tabela de usuários
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    is_active BOOLEAN DEFAULT TRUE,
    last_login TIMESTAMP NULL
);

-- Tabela de dispositivos IoT
CREATE TABLE IF NOT EXISTS devices (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    name VARCHAR(255) NOT NULL,
    device_id VARCHAR(255) NOT NULL, -- MAC address ou ID único
    mac_address VARCHAR(17) NOT NULL,
    device_type VARCHAR(100) DEFAULT 'ESP32',
    service_uuid VARCHAR(36) DEFAULT '12345678-1234-1234-1234-1234567890ab',
    status ENUM('not_configured', 'configured', 'configuring', 'error', 'offline', 'online') DEFAULT 'not_configured',
    last_ssid VARCHAR(255) NULL,
    last_configured TIMESTAMP NULL,
    rssi INT NULL,
    is_connected BOOLEAN DEFAULT FALSE,
    location VARCHAR(255) NULL,
    description TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_user_device (user_id, device_id)
);

-- Tabela de ações/comandos para dispositivos
CREATE TABLE IF NOT EXISTS device_actions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    device_id INT NOT NULL,
    action_type VARCHAR(100) NOT NULL, -- 'wifi_config', 'command', 'sensor_read', etc.
    action_data JSON NOT NULL,
    status ENUM('pending', 'sent', 'completed', 'failed') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    executed_at TIMESTAMP NULL,
    response_data JSON NULL,
    error_message TEXT NULL,
    FOREIGN KEY (device_id) REFERENCES devices(id) ON DELETE CASCADE
);

-- Tabela de dados de sensores (opcional para futuro)
CREATE TABLE IF NOT EXISTS sensor_data (
    id INT AUTO_INCREMENT PRIMARY KEY,
    device_id INT NOT NULL,
    sensor_type VARCHAR(100) NOT NULL, -- 'temperature', 'humidity', 'motion', etc.
    value DECIMAL(10,4) NOT NULL,
    unit VARCHAR(20) NULL,
    timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (device_id) REFERENCES devices(id) ON DELETE CASCADE
);

-- Tabela de configurações Wi-Fi (histórico)
CREATE TABLE IF NOT EXISTS wifi_configurations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    device_id INT NOT NULL,
    ssid VARCHAR(255) NOT NULL,
    configured_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    success BOOLEAN DEFAULT FALSE,
    error_message TEXT NULL,
    FOREIGN KEY (device_id) REFERENCES devices(id) ON DELETE CASCADE
);

-- Tabela de logs do sistema
CREATE TABLE IF NOT EXISTS system_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL,
    device_id INT NULL,
    action VARCHAR(255) NOT NULL,
    details JSON NULL,
    ip_address VARCHAR(45) NULL,
    user_agent TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (device_id) REFERENCES devices(id) ON DELETE SET NULL
);

-- Índices para melhor performance
CREATE INDEX idx_devices_user_id ON devices(user_id);
CREATE INDEX idx_devices_status ON devices(status);
CREATE INDEX idx_device_actions_device_id ON device_actions(device_id);
CREATE INDEX idx_device_actions_status ON device_actions(status);
CREATE INDEX idx_sensor_data_device_id ON sensor_data(device_id);
CREATE INDEX idx_sensor_data_timestamp ON sensor_data(timestamp);
CREATE INDEX idx_wifi_configurations_device_id ON wifi_configurations(device_id);
CREATE INDEX idx_system_logs_user_id ON system_logs(user_id);
CREATE INDEX idx_system_logs_created_at ON system_logs(created_at);

-- Inserir usuário admin padrão (senha: admin123)
INSERT INTO users (name, email, password_hash) VALUES 
('Administrador', 'admin@iot.local', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi');

-- Inserir alguns dispositivos de exemplo
INSERT INTO devices (user_id, name, device_id, mac_address, status, location) VALUES 
(1, 'Sensor Sala', 'ESP32_001', '24:6F:28:AB:CD:EF', 'configured', 'Sala de Estar'),
(1, 'Controlador Quarto', 'ESP32_002', '24:6F:28:AB:CD:F0', 'not_configured', 'Quarto Principal'),
(1, 'Monitor Cozinha', 'ESP32_003', '24:6F:28:AB:CD:F1', 'online', 'Cozinha');

-- Inserir algumas ações de exemplo
INSERT INTO device_actions (device_id, action_type, action_data, status) VALUES 
(1, 'wifi_config', '{"ssid": "MinhaRede", "timestamp": "2024-01-15 10:30:00"}', 'completed'),
(2, 'command', '{"command": "turn_on_led", "parameters": {"brightness": 80}}', 'pending'),
(3, 'sensor_read', '{"sensors": ["temperature", "humidity"]}', 'completed');

-- Inserir alguns dados de sensores de exemplo
INSERT INTO sensor_data (device_id, sensor_type, value, unit) VALUES 
(1, 'temperature', 23.5, '°C'),
(1, 'humidity', 65.2, '%'),
(3, 'temperature', 25.1, '°C'),
(3, 'humidity', 58.7, '%');

COMMIT;