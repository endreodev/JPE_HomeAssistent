<?php
/**
 * API de Dados de Sensores
 * Endpoints: /sensors (GET, POST), /sensors/device/{device_id} (GET), /sensors/latest/{device_id}/{sensor_type} (GET)
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, PUT, DELETE');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

require_once '../config/database.php';
require_once '../classes/User.php';
require_once '../classes/Device.php';
require_once '../classes/SensorData.php';

// Função para resposta JSON
function sendResponse($status, $data = null, $message = '') {
    http_response_code($status);
    echo json_encode([
        'success' => $status < 400,
        'message' => $message,
        'data' => $data,
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    exit;
}

// Função para obter dados do corpo da requisição
function getRequestData() {
    return json_decode(file_get_contents('php://input'), true);
}

// Função para validar autenticação
function validateAuth($user) {
    $headers = getallheaders();
    $auth_header = $headers['Authorization'] ?? '';
    
    if (preg_match('/Bearer\s+(.+)/', $auth_header, $matches)) {
        $token = $matches[1];
        $user_data = $user->validateToken($token);
        
        if ($user_data) {
            return $user_data['user_id'];
        }
    }
    
    sendResponse(401, null, 'Token de autorização inválido');
}

// Conectar ao banco
try {
    $database = new Database();
    $db = $database->getConnection();
} catch (Exception $e) {
    sendResponse(500, null, 'Erro de conexão com o banco de dados');
}

// Obter método da requisição
$method = $_SERVER['REQUEST_METHOD'];
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$path_parts = explode('/', trim($path, '/'));

// Validar se é uma requisição para sensors
if (!isset($path_parts[1]) || $path_parts[1] !== 'sensors') {
    sendResponse(404, null, 'Endpoint não encontrado');
}

// Analisar rota
$sub_action = $path_parts[2] ?? null;
$device_id = null;
$sensor_type = null;

if ($sub_action === 'device' && isset($path_parts[3])) {
    $device_id = $path_parts[3];
} elseif ($sub_action === 'latest' && isset($path_parts[3]) && isset($path_parts[4])) {
    $device_id = $path_parts[3];
    $sensor_type = $path_parts[4];
}

// Instanciar classes
$user = new User($db);
$device = new Device($db);
$sensorData = new SensorData($db);

// Validar autenticação
$user_id = validateAuth($user);

switch ($method) {
    case 'GET':
        handleGet($sub_action, $device_id, $sensor_type, $sensorData, $device, $user_id);
        break;
    case 'POST':
        handlePost($sensorData, $device, $user_id);
        break;
    default:
        sendResponse(405, null, 'Método não permitido');
}

/**
 * Processar requisições GET
 */
function handleGet($sub_action, $device_id, $sensor_type, $sensorData, $device, $user_id) {
    switch ($sub_action) {
        case 'device':
            getDeviceSensorData($device_id, $sensorData, $device, $user_id);
            break;
        case 'latest':
            getLatestSensorValue($device_id, $sensor_type, $sensorData, $device, $user_id);
            break;
        case 'stats':
            if ($device_id) {
                getDeviceStatistics($device_id, $sensorData, $device, $user_id);
            } else {
                sendResponse(400, null, 'device_id é obrigatório para estatísticas');
            }
            break;
        default:
            getUserSensorData($sensorData, $user_id);
    }
}

/**
 * Processar requisições POST
 */
function handlePost($sensorData, $device, $user_id) {
    $data = getRequestData();
    
    if (isset($data['batch']) && is_array($data['batch'])) {
        saveBatchSensorData($sensorData, $device, $data['batch'], $user_id);
    } else {
        saveSensorData($sensorData, $device, $data, $user_id);
    }
}

/**
 * Obter dados de sensores do usuário
 */
function getUserSensorData($sensorData, $user_id) {
    $sensor_type = $_GET['sensor_type'] ?? null;
    $limit = $_GET['limit'] ?? 100;
    
    $data = $sensorData->findByUserId($user_id, $sensor_type, $limit);
    
    sendResponse(200, [
        'sensor_data' => $data,
        'total' => count($data)
    ], 'Dados de sensores obtidos com sucesso');
}

/**
 * Obter dados de sensores de um dispositivo
 */
function getDeviceSensorData($device_id, $sensorData, $device, $user_id) {
    // Validar se o dispositivo pertence ao usuário
    if (!$device->findById($device_id, $user_id)) {
        sendResponse(404, null, 'Dispositivo não encontrado');
    }
    
    $sensor_type = $_GET['sensor_type'] ?? null;
    $limit = $_GET['limit'] ?? 100;
    $from_date = $_GET['from_date'] ?? null;
    $to_date = $_GET['to_date'] ?? null;
    $interval = $_GET['interval'] ?? null;
    
    if ($interval) {
        // Dados agregados
        $data = $sensorData->getAggregatedData($device_id, $sensor_type, $interval, $from_date, $to_date);
        $message = 'Dados agregados obtidos com sucesso';
    } else {
        // Dados brutos
        $data = $sensorData->findByDeviceId($device_id, $sensor_type, $limit, $from_date, $to_date);
        $message = 'Dados de sensores do dispositivo obtidos com sucesso';
    }
    
    sendResponse(200, [
        'sensor_data' => $data,
        'device_id' => $device_id,
        'sensor_type' => $sensor_type,
        'total' => count($data)
    ], $message);
}

/**
 * Obter último valor de um sensor
 */
function getLatestSensorValue($device_id, $sensor_type, $sensorData, $device, $user_id) {
    // Validar se o dispositivo pertence ao usuário
    if (!$device->findById($device_id, $user_id)) {
        sendResponse(404, null, 'Dispositivo não encontrado');
    }
    
    $data = $sensorData->getLatestValue($device_id, $sensor_type, $user_id);
    
    if ($data) {
        sendResponse(200, [
            'sensor_data' => $data,
            'device_id' => $device_id,
            'sensor_type' => $sensor_type
        ], 'Último valor do sensor obtido com sucesso');
    } else {
        sendResponse(404, null, 'Nenhum dado encontrado para este sensor');
    }
}

/**
 * Obter estatísticas do dispositivo
 */
function getDeviceStatistics($device_id, $sensorData, $device, $user_id) {
    // Validar se o dispositivo pertence ao usuário
    if (!$device->findById($device_id, $user_id)) {
        sendResponse(404, null, 'Dispositivo não encontrado');
    }
    
    $days = $_GET['days'] ?? 7;
    $stats = $sensorData->getDeviceStatistics($device_id, $days);
    
    sendResponse(200, [
        'statistics' => $stats,
        'device_id' => $device_id,
        'period_days' => $days
    ], 'Estatísticas do dispositivo obtidas com sucesso');
}

/**
 * Salvar dados de sensor
 */
function saveSensorData($sensorData, $device, $data, $user_id) {
    // Validar dados obrigatórios
    if (empty($data['device_id']) || empty($data['sensor_type']) || !isset($data['sensor_value'])) {
        sendResponse(400, null, 'device_id, sensor_type e sensor_value são obrigatórios');
    }

    // Validar se o dispositivo pertence ao usuário
    if (!$device->findById($data['device_id'], $user_id)) {
        sendResponse(403, null, 'Dispositivo não encontrado ou não autorizado');
    }

    // Definir dados do sensor
    $sensorData->device_id = $data['device_id'];
    $sensorData->sensor_type = $data['sensor_type'];
    $sensorData->sensor_value = $data['sensor_value'];
    $sensorData->unit = $data['unit'] ?? null;
    $sensorData->metadata = $data['metadata'] ?? null;

    // Tentar salvar
    if ($sensorData->save()) {
        sendResponse(201, [
            'sensor_data' => $sensorData->toArray()
        ], 'Dados do sensor salvos com sucesso');
    } else {
        sendResponse(400, null, 'Erro ao salvar dados do sensor');
    }
}

/**
 * Salvar múltiplos dados de sensores
 */
function saveBatchSensorData($sensorData, $device, $batch_data, $user_id) {
    // Validar formato do batch
    if (empty($batch_data) || !is_array($batch_data)) {
        sendResponse(400, null, 'Batch deve ser um array não vazio');
    }

    // Validar cada item do batch
    $validated_data = [];
    foreach ($batch_data as $index => $data) {
        if (empty($data['device_id']) || empty($data['sensor_type']) || !isset($data['sensor_value'])) {
            sendResponse(400, null, "Item $index: device_id, sensor_type e sensor_value são obrigatórios");
        }

        // Validar se o dispositivo pertence ao usuário
        if (!$device->findById($data['device_id'], $user_id)) {
            sendResponse(403, null, "Item $index: Dispositivo não encontrado ou não autorizado");
        }

        $validated_data[] = [
            'device_id' => $data['device_id'],
            'sensor_type' => $data['sensor_type'],
            'sensor_value' => $data['sensor_value'],
            'unit' => $data['unit'] ?? null,
            'metadata' => $data['metadata'] ?? null
        ];
    }

    // Tentar salvar batch
    if ($sensorData->saveBatch($validated_data)) {
        sendResponse(201, [
            'saved_count' => count($validated_data)
        ], 'Dados dos sensores salvos em lote com sucesso');
    } else {
        sendResponse(400, null, 'Erro ao salvar dados dos sensores em lote');
    }
}
?>