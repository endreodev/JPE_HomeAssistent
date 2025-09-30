<?php
/**
 * Configurações do Banco de Dados
 * IoT Home Assistant Backend
 */

// Incluir configurações de produção
require_once __DIR__ . '/production.php';

// Obter configurações baseadas no ambiente
$dbConfig = getDatabaseConfig();
$jwtConfig = getJWTConfig();

// Configurações do MySQL
define('DB_HOST', $dbConfig['host']);
define('DB_NAME', $dbConfig['dbname']);
define('DB_USER', $dbConfig['username']);
define('DB_PASS', $dbConfig['password']);
define('DB_CHARSET', 'utf8mb4');

// Configurações JWT
define('JWT_SECRET', $jwtConfig['secret']);
define('JWT_EXPIRE', $jwtConfig['access_expire']);
define('JWT_REFRESH_EXPIRE', $jwtConfig['refresh_expire']);

// Configurações gerais
define('API_VERSION', '1.0');
define('CORS_ORIGIN', '*'); // Em produção, especificar domínios

// Headers padrão para API
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: ' . CORS_ORIGIN);
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Responder OPTIONS para CORS
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

/**
 * Classe para conexão com banco de dados
 */
class Database {
    private $host = DB_HOST;
    private $db_name = DB_NAME;
    private $username = DB_USER;
    private $password = DB_PASS;
    private $charset = DB_CHARSET;
    public $conn;

    public function getConnection() {
        $this->conn = null;
        
        try {
            $dsn = "mysql:host=" . $this->host . ";dbname=" . $this->db_name . ";charset=" . $this->charset;
            $this->conn = new PDO($dsn, $this->username, $this->password);
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch(PDOException $exception) {
            error_log("Connection error: " . $exception->getMessage());
        }

        return $this->conn;
    }
}

/**
 * Função para retornar resposta JSON
 */
function jsonResponse($data, $status_code = 200) {
    http_response_code($status_code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit();
}

/**
 * Função para validar JWT
 */
function validateJWT($token) {
    if (!$token) {
        return false;
    }
    
    // Remove "Bearer " se presente
    if (strpos($token, 'Bearer ') === 0) {
        $token = substr($token, 7);
    }
    
    $parts = explode('.', $token);
    if (count($parts) !== 3) {
        return false;
    }
    
    $header = json_decode(base64_decode($parts[0]), true);
    $payload = json_decode(base64_decode($parts[1]), true);
    $signature = $parts[2];
    
    // Verificar se não expirou
    if (isset($payload['exp']) && $payload['exp'] < time()) {
        return false;
    }
    
    // Verificar assinatura
    $expected_signature = base64_encode(hash_hmac('sha256', $parts[0] . '.' . $parts[1], JWT_SECRET, true));
    
    if ($signature !== $expected_signature) {
        return false;
    }
    
    return $payload;
}

/**
 * Função para gerar JWT
 */
function generateJWT($user_id, $email) {
    $header = json_encode(['typ' => 'JWT', 'alg' => 'HS256']);
    $payload = json_encode([
        'user_id' => $user_id,
        'email' => $email,
        'iat' => time(),
        'exp' => time() + JWT_EXPIRE
    ]);
    
    $base64_header = base64_encode($header);
    $base64_payload = base64_encode($payload);
    
    $signature = base64_encode(hash_hmac('sha256', $base64_header . '.' . $base64_payload, JWT_SECRET, true));
    
    return $base64_header . '.' . $base64_payload . '.' . $signature;
}

/**
 * Função para obter dados JSON do request
 */
function getJsonInput() {
    $input = file_get_contents('php://input');
    return json_decode($input, true);
}

/**
 * Função para logs
 */
function logMessage($message, $level = 'INFO') {
    $timestamp = date('Y-m-d H:i:s');
    $log_entry = "[$timestamp] [$level] $message" . PHP_EOL;
    file_put_contents('../logs/api.log', $log_entry, FILE_APPEND | LOCK_EX);
}
?>