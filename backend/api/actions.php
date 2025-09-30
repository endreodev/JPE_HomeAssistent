<?php
/**
 * API de Ações de Dispositivos
 * Endpoints: /actions (GET, POST), /actions/{id} (GET, PUT), /actions/device/{device_id} (GET)
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, PUT, DELETE');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

require_once '../config/database.php';
require_once '../classes/User.php';
require_once '../classes/Device.php';
require_once '../classes/DeviceAction.php';
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

// Validar se é uma requisição para actions
if (!isset($path_parts[1]) || $path_parts[1] !== 'actions') {
    sendResponse(404, null, 'Endpoint não encontrado');
}

// Analisar rota
$action_id = null;
$device_id = null;
$sub_action = $path_parts[2] ?? null;

if (is_numeric($sub_action)) {
    $action_id = $sub_action;
} elseif ($sub_action === 'device' && isset($path_parts[3])) {
    $device_id = $path_parts[3];
}

// Instanciar classes
$user = new User($db);
$device = new Device($db);
$deviceAction = new DeviceAction($db);

// Validar autenticação
$user_id = validateAuth($user);

switch ($method) {
    case 'GET':
        handleGet($action_id, $device_id, $deviceAction, $user_id);
        break;
    case 'POST':
        handlePost($deviceAction, $device, $user_id, $db);
        break;
    case 'PUT':
        handlePut($action_id, $deviceAction, $user_id, $db);
        break;
    default:
        sendResponse(405, null, 'Método não permitido');
}

/**
 * Processar requisições GET
 */
function handleGet($action_id, $device_id, $deviceAction, $user_id) {
    if ($action_id) {
        // Buscar ação específica
        getAction($action_id, $deviceAction, $user_id);
    } elseif ($device_id) {
        // Buscar ações de um dispositivo específico
        getDeviceActions($device_id, $deviceAction, $user_id);
    } else {
        // Listar ações pendentes do usuário
        getPendingActions($deviceAction, $user_id);
    }
}

/**
 * Processar requisições POST
 */
function handlePost($deviceAction, $device, $user_id, $db) {
    $data = getRequestData();
    createAction($deviceAction, $device, $data, $user_id, $db);
}

/**
 * Processar requisições PUT
 */
function handlePut($action_id, $deviceAction, $user_id, $db) {
    if (!$action_id) {
        sendResponse(400, null, 'ID da ação é obrigatório');
    }
    
    $data = getRequestData();
    updateActionStatus($action_id, $deviceAction, $data, $user_id, $db);
}

/**
 * Listar ações pendentes do usuário
 */
function getPendingActions($deviceAction, $user_id) {
    $actions = $deviceAction->findPendingByUserId($user_id);
    
    sendResponse(200, [
        'actions' => $actions,
        'total' => count($actions)
    ], 'Ações pendentes obtidas com sucesso');
}

/**
 * Obter ação específica
 */
function getAction($action_id, $deviceAction, $user_id) {
    if ($deviceAction->findById($action_id, $user_id)) {
        sendResponse(200, [
            'action' => $deviceAction->toArray()
        ], 'Ação obtida com sucesso');
    } else {
        sendResponse(404, null, 'Ação não encontrada');
    }
}

/**
 * Obter ações de um dispositivo
 */
function getDeviceActions($device_id, $deviceAction, $user_id) {
    // Validar se o dispositivo pertence ao usuário
    $device = new Device($deviceAction->conn);
    if (!$device->findById($device_id, $user_id)) {
        sendResponse(404, null, 'Dispositivo não encontrado');
    }
    
    $limit = $_GET['limit'] ?? 50;
    $actions = $deviceAction->findByDeviceId($device_id, $limit);
    
    sendResponse(200, [
        'actions' => $actions,
        'device_id' => $device_id,
        'total' => count($actions)
    ], 'Ações do dispositivo obtidas com sucesso');
}

/**
 * Criar nova ação
 */
function createAction($deviceAction, $device, $data, $user_id, $db) {
    // Validar dados obrigatórios
    if (empty($data['device_id']) || empty($data['action_type'])) {
        sendResponse(400, null, 'device_id e action_type são obrigatórios');
    }

    // Validar se o dispositivo pertence ao usuário
    if (!$device->findById($data['device_id'], $user_id)) {
        sendResponse(403, null, 'Dispositivo não encontrado ou não autorizado');
    }

    // Definir dados da ação
    $deviceAction->device_id = $data['device_id'];
    $deviceAction->action_type = $data['action_type'];
    $deviceAction->action_data = $data['action_data'] ?? null;
    $deviceAction->status = 'pending';

    // Tentar criar ação
    if ($deviceAction->create()) {
        // Log da ação
        SystemLog::logDeviceAction($db, $user_id, $data['device_id'], $data['action_type'], $data['action_data'] ?? null);
        
        sendResponse(201, [
            'action' => $deviceAction->toArray()
        ], 'Ação criada com sucesso');
    } else {
        sendResponse(400, null, 'Erro ao criar ação');
    }
}

/**
 * Atualizar status da ação
 */
function updateActionStatus($action_id, $deviceAction, $data, $user_id, $db) {
    // Buscar ação
    if (!$deviceAction->findById($action_id, $user_id)) {
        sendResponse(404, null, 'Ação não encontrada');
    }

    // Validar status
    $valid_statuses = ['pending', 'sent', 'completed', 'failed'];
    if (empty($data['status']) || !in_array($data['status'], $valid_statuses)) {
        sendResponse(400, null, 'Status inválido. Deve ser: ' . implode(', ', $valid_statuses));
    }

    // Atualizar status
    $response_data = $data['response_data'] ?? null;
    $error_message = $data['error_message'] ?? null;
    
    if ($deviceAction->updateStatus($data['status'], $response_data, $error_message)) {
        sendResponse(200, [
            'action' => $deviceAction->toArray()
        ], 'Status da ação atualizado com sucesso');
    } else {
        sendResponse(400, null, 'Erro ao atualizar status da ação');
    }
}
?>