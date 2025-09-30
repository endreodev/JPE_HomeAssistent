<?php
/**
 * API de Autenticação
 * Endpoints: /auth/register, /auth/login, /auth/refresh, /auth/logout
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, PUT, DELETE');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

require_once '../config/database.php';
require_once '../classes/User.php';
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

// Validar se é uma requisição para auth
if (!isset($path_parts[1]) || $path_parts[1] !== 'auth') {
    sendResponse(404, null, 'Endpoint não encontrado');
}

$action = $path_parts[2] ?? null;

// Instanciar classes
$user = new User($db);

switch ($method) {
    case 'POST':
        handlePost($action, $user, $db);
        break;
    case 'GET':
        handleGet($action, $user, $db);
        break;
    default:
        sendResponse(405, null, 'Método não permitido');
}

/**
 * Processar requisições POST
 */
function handlePost($action, $user, $db) {
    $data = getRequestData();
    
    switch ($action) {
        case 'register':
            register($user, $data, $db);
            break;
        case 'login':
            login($user, $data, $db);
            break;
        case 'refresh':
            refreshToken($user, $data, $db);
            break;
        case 'logout':
            logout($user, $data, $db);
            break;
        default:
            sendResponse(404, null, 'Ação não encontrada');
    }
}

/**
 * Processar requisições GET
 */
function handleGet($action, $user, $db) {
    switch ($action) {
        case 'me':
            getProfile($user, $db);
            break;
        default:
            sendResponse(404, null, 'Ação não encontrada');
    }
}

/**
 * Registrar novo usuário
 */
function register($user, $data, $db) {
    // Validar dados obrigatórios
    if (empty($data['username']) || empty($data['email']) || empty($data['password'])) {
        sendResponse(400, null, 'Username, email e password são obrigatórios');
    }

    // Validar formato do email
    if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
        sendResponse(400, null, 'Formato de email inválido');
    }

    // Validar força da senha
    if (strlen($data['password']) < 6) {
        sendResponse(400, null, 'Password deve ter pelo menos 6 caracteres');
    }

    // Definir dados do usuário
    $user->username = $data['username'];
    $user->email = $data['email'];
    $user->password = $data['password'];
    $user->full_name = $data['full_name'] ?? null;

    // Tentar criar usuário
    if ($user->create()) {
        // Log da ação
        SystemLog::logUserUpdate($db, $user->id);
        
        // Gerar tokens
        $tokens = $user->generateTokens();
        
        sendResponse(201, [
            'user' => [
                'id' => $user->id,
                'username' => $user->username,
                'email' => $user->email,
                'full_name' => $user->full_name,
                'created_at' => $user->created_at
            ],
            'tokens' => $tokens
        ], 'Usuário criado com sucesso');
    } else {
        sendResponse(400, null, 'Erro ao criar usuário. Username ou email já existem.');
    }
}

/**
 * Login do usuário
 */
function login($user, $data, $db) {
    // Validar dados obrigatórios
    if (empty($data['login']) || empty($data['password'])) {
        sendResponse(400, null, 'Login e password são obrigatórios');
    }

    // Tentar fazer login
    if ($user->login($data['login'], $data['password'])) {
        // Log de login bem-sucedido
        SystemLog::logLogin($db, $user->id, true);
        
        // Gerar tokens
        $tokens = $user->generateTokens();
        
        sendResponse(200, [
            'user' => [
                'id' => $user->id,
                'username' => $user->username,
                'email' => $user->email,
                'full_name' => $user->full_name,
                'last_login' => $user->last_login
            ],
            'tokens' => $tokens
        ], 'Login realizado com sucesso');
    } else {
        // Log de login falhado
        if ($user->id) {
            SystemLog::logLogin($db, $user->id, false);
        }
        
        sendResponse(401, null, 'Credenciais inválidas');
    }
}

/**
 * Renovar token de acesso
 */
function refreshToken($user, $data, $db) {
    // Validar refresh token
    if (empty($data['refresh_token'])) {
        sendResponse(400, null, 'Refresh token é obrigatório');
    }

    // Tentar renovar token
    $tokens = $user->refreshToken($data['refresh_token']);
    
    if ($tokens) {
        sendResponse(200, [
            'tokens' => $tokens
        ], 'Token renovado com sucesso');
    } else {
        sendResponse(401, null, 'Refresh token inválido ou expirado');
    }
}

/**
 * Logout do usuário
 */
function logout($user, $data, $db) {
    // Obter token do header Authorization
    $headers = getallheaders();
    $auth_header = $headers['Authorization'] ?? '';
    
    if (preg_match('/Bearer\s+(.+)/', $auth_header, $matches)) {
        $token = $matches[1];
        
        // Validar token e obter usuário
        $user_data = $user->validateToken($token);
        
        if ($user_data) {
            // Log de logout
            SystemLog::logLogout($db, $user_data['user_id']);
            
            // Revogar token (implementação simples - em produção usar blacklist)
            sendResponse(200, null, 'Logout realizado com sucesso');
        } else {
            sendResponse(401, null, 'Token inválido');
        }
    } else {
        sendResponse(400, null, 'Token de autorização não encontrado');
    }
}

/**
 * Obter perfil do usuário logado
 */
function getProfile($user, $db) {
    // Obter token do header Authorization
    $headers = getallheaders();
    $auth_header = $headers['Authorization'] ?? '';
    
    if (preg_match('/Bearer\s+(.+)/', $auth_header, $matches)) {
        $token = $matches[1];
        
        // Validar token e obter usuário
        $user_data = $user->validateToken($token);
        
        if ($user_data) {
            // Buscar dados completos do usuário
            if ($user->findById($user_data['user_id'])) {
                sendResponse(200, [
                    'user' => [
                        'id' => $user->id,
                        'username' => $user->username,
                        'email' => $user->email,
                        'full_name' => $user->full_name,
                        'created_at' => $user->created_at,
                        'last_login' => $user->last_login
                    ]
                ], 'Perfil obtido com sucesso');
            } else {
                sendResponse(404, null, 'Usuário não encontrado');
            }
        } else {
            sendResponse(401, null, 'Token inválido');
        }
    } else {
        sendResponse(401, null, 'Token de autorização não encontrado');
    }
}
?>