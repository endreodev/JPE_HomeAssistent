<?php
/**
 * API de Dispositivos
 * Endpoints: /devices (GET, POST), /devices/{id} (GET, PUT, DELETE)
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, PUT, DELETE');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

require_once '../config/database.php';
require_once '../classes/User.php';
require_once '../classes/Device.php';
require_once '../classes/SystemLog.php';

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

// Validar se é uma requisição para devices
if (!isset($path_parts[1]) || $path_parts[1] !== 'devices') {
    sendResponse(404, null, 'Endpoint não encontrado');
}

$device_id = $path_parts[2] ?? null;

// Instanciar classes
$user = new User($db);
$device = new Device($db);

// Validar autenticação
$user_id = validateAuth($user);

switch ($method) {
    case 'GET':
        handleGet($device_id, $device, $user_id);
        break;
    case 'POST':
        handlePost($device, $user_id, $db);
        break;
    case 'PUT':
        handlePut($device_id, $device, $user_id, $db);
        break;
    case 'DELETE':
        handleDelete($device_id, $device, $user_id, $db);
        break;
    default:
        sendResponse(405, null, 'Método não permitido');
}

/**
 * Processar requisições GET
 */
function handleGet($device_id, $device, $user_id) {
    if ($device_id) {
        // Buscar dispositivo específico
        getDevice($device_id, $device, $user_id);
    } else {
        // Listar dispositivos do usuário
        getDevices($device, $user_id);
    }
}

/**
 * Processar requisições POST
 */
function handlePost($device, $user_id, $db) {
    $data = getRequestData();
    createDevice($device, $data, $user_id, $db);
}

/**
 * Processar requisições PUT
 */
function handlePut($device_id, $device, $user_id, $db) {
    if (!$device_id) {
        sendResponse(400, null, 'ID do dispositivo é obrigatório');
    }
    
    $data = getRequestData();
    updateDevice($device_id, $device, $data, $user_id, $db);
}

/**
 * Processar requisições DELETE
 */
function handleDelete($device_id, $device, $user_id, $db) {
    if (!$device_id) {
        sendResponse(400, null, 'ID do dispositivo é obrigatório');
    }
    
    deleteDevice($device_id, $device, $user_id, $db);
}

/**
 * Listar dispositivos do usuário
 */
function getDevices($device, $user_id) {
    $devices = $device->findByUserId($user_id);
    
    sendResponse(200, [
        'devices' => $devices,
        'total' => count($devices)
    ], 'Dispositivos obtidos com sucesso');
}

/**
 * Obter dispositivo específico
 */
function getDevice($device_id, $device, $user_id) {
    if ($device->findById($device_id, $user_id)) {
        sendResponse(200, [
            'device' => $device->toArray()
        ], 'Dispositivo obtido com sucesso');
    } else {
        sendResponse(404, null, 'Dispositivo não encontrado');
    }
}

/**
 * Criar novo dispositivo
 */
function createDevice($device, $data, $user_id, $db) {
    // Validar dados obrigatórios
    if (empty($data['name']) || empty($data['mac_address'])) {
        sendResponse(400, null, 'Nome e MAC address são obrigatórios');
    }

    // Definir dados do dispositivo
    $device->user_id = $user_id;
    $device->name = $data['name'];
    $device->mac_address = $data['mac_address'];
    $device->device_type = $data['device_type'] ?? 'generic';
    $device->status = $data['status'] ?? 'offline';
    $device->firmware_version = $data['firmware_version'] ?? null;
    $device->last_seen = $data['last_seen'] ?? null;
    $device->settings = $data['settings'] ?? null;

    // Tentar criar dispositivo
    if ($device->create()) {
        // Log da ação
        SystemLog::logDeviceCreate($db, $user_id, $device->id);
        
        sendResponse(201, [
            'device' => $device->toArray()
        ], 'Dispositivo criado com sucesso');
    } else {
        sendResponse(400, null, 'Erro ao criar dispositivo. MAC address pode já existir.');
    }
}

/**
 * Atualizar dispositivo
 */
function updateDevice($device_id, $device, $data, $user_id, $db) {
    // Buscar dispositivo
    if (!$device->findById($device_id, $user_id)) {
        sendResponse(404, null, 'Dispositivo não encontrado');
    }

    // Atualizar apenas campos fornecidos
    if (isset($data['name'])) {
        $device->name = $data['name'];
    }
    if (isset($data['device_type'])) {
        $device->device_type = $data['device_type'];
    }
    if (isset($data['status'])) {
        $device->status = $data['status'];
    }
    if (isset($data['firmware_version'])) {
        $device->firmware_version = $data['firmware_version'];
    }
    if (isset($data['last_seen'])) {
        $device->last_seen = $data['last_seen'];
    }
    if (isset($data['settings'])) {
        $device->settings = $data['settings'];
    }

    // Tentar atualizar
    if ($device->update()) {
        // Log da ação
        SystemLog::logDeviceUpdate($db, $user_id, $device_id);
        
        sendResponse(200, [
            'device' => $device->toArray()
        ], 'Dispositivo atualizado com sucesso');
    } else {
        sendResponse(400, null, 'Erro ao atualizar dispositivo');
    }
}

/**
 * Deletar dispositivo
 */
function deleteDevice($device_id, $device, $user_id, $db) {
    // Buscar dispositivo
    if (!$device->findById($device_id, $user_id)) {
        sendResponse(404, null, 'Dispositivo não encontrado');
    }

    // Tentar deletar
    if ($device->delete()) {
        // Log da ação
        SystemLog::logDeviceDelete($db, $user_id, $device_id);
        
        sendResponse(200, null, 'Dispositivo deletado com sucesso');
    } else {
        sendResponse(400, null, 'Erro ao deletar dispositivo');
    }
}
?>