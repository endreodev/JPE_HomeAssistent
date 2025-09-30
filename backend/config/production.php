<?php
/**
 * Configurações de Produção
 * Use este arquivo para configurações específicas do servidor de produção
 */

// ======================
// CONFIGURAÇÕES DO BANCO
// ======================
define('PROD_DB_HOST', '62.72.62.1');
define('PROD_DB_NAME', 'u454452574_ibyt_nivel');
define('PROD_DB_USER', 'u454452574_ibyt_nivel');
define('PROD_DB_PASS', 'Terra@15700');
define('PROD_DB_PORT', '3306');

// ======================
// CONFIGURAÇÕES DE SEGURANÇA
// ======================

// Chave JWT super segura (MUDE ESTA CHAVE!)
define('PROD_JWT_SECRET', 'ibyt_nivel_jwt_key_2024_super_secure_change_this_key');

// Tempo de expiração dos tokens (em segundos)
define('PROD_JWT_ACCESS_EXPIRE', 3600);    // 1 hora
define('PROD_JWT_REFRESH_EXPIRE', 604800); // 7 dias

// ======================
// CONFIGURAÇÕES DE CORS
// ======================

// Permitir origens específicas (ajuste conforme necessário)
define('PROD_CORS_ORIGINS', [
    'https://seuapp.com',
    'https://www.seuapp.com',
    'http://localhost:3000', // Para desenvolvimento
    'capacitor://localhost', // Para app Capacitor
    'ionic://localhost'      // Para app Ionic
]);

// ======================
// CONFIGURAÇÕES DE EMAIL
// ======================
define('PROD_SMTP_HOST', 'smtp.gmail.com');
define('PROD_SMTP_PORT', 587);
define('PROD_SMTP_USERNAME', 'seu_email@gmail.com');
define('PROD_SMTP_PASSWORD', 'sua_senha_app');
define('PROD_FROM_EMAIL', 'noreply@seudominio.com');
define('PROD_FROM_NAME', 'IoT Home Assistant');

// ======================
// CONFIGURAÇÕES DE LOG
// ======================
define('PROD_LOG_LEVEL', 'ERROR'); // DEBUG, INFO, WARNING, ERROR
define('PROD_LOG_FILE', '/logs/api.log');
define('PROD_ERROR_REPORTING', E_ERROR | E_WARNING);

// ======================
// CONFIGURAÇÕES DE UPLOAD
// ======================
define('PROD_MAX_UPLOAD_SIZE', 5 * 1024 * 1024); // 5MB
define('PROD_UPLOAD_DIR', '/uploads/');
define('PROD_ALLOWED_EXTENSIONS', ['jpg', 'jpeg', 'png', 'gif', 'pdf']);

// ======================
// CONFIGURAÇÕES DE RATE LIMITING
// ======================
define('PROD_RATE_LIMIT_REQUESTS', 100); // Requisições por minuto
define('PROD_RATE_LIMIT_WINDOW', 60);    // Janela em segundos

// ======================
// FUNÇÕES AUXILIARES
// ======================

/**
 * Verificar se estamos em produção
 */
function isProduction() {
    return ($_SERVER['HTTP_HOST'] ?? '') !== 'localhost';
}

/**
 * Configurar headers de segurança para produção
 */
function setSecurityHeaders() {
    if (isProduction()) {
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: DENY');
        header('X-XSS-Protection: 1; mode=block');
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
        header('Referrer-Policy: strict-origin-when-cross-origin');
        
        // CSP básico
        header("Content-Security-Policy: default-src 'self'; script-src 'self'; style-src 'self' 'unsafe-inline'");
    }
}

/**
 * Configurar CORS baseado no ambiente
 */
function configureCORS() {
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    
    if (isProduction()) {
        // Em produção, verificar origem específica
        if (in_array($origin, PROD_CORS_ORIGINS)) {
            header("Access-Control-Allow-Origin: $origin");
        }
    } else {
        // Em desenvolvimento, permitir qualquer origem
        header('Access-Control-Allow-Origin: *');
    }
    
    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
    header('Access-Control-Allow-Credentials: true');
}

/**
 * Configurar relatório de erros baseado no ambiente
 */
function configureErrorReporting() {
    if (isProduction()) {
        error_reporting(PROD_ERROR_REPORTING);
        ini_set('display_errors', '0');
        ini_set('log_errors', '1');
        ini_set('error_log', __DIR__ . PROD_LOG_FILE);
    } else {
        error_reporting(E_ALL);
        ini_set('display_errors', '1');
    }
}

/**
 * Obter configurações do banco baseado no ambiente
 */
function getDatabaseConfig() {
    if (isProduction()) {
        return [
            'host' => PROD_DB_HOST,
            'dbname' => PROD_DB_NAME,
            'username' => PROD_DB_USER,
            'password' => PROD_DB_PASS,
            'port' => PROD_DB_PORT
        ];
    } else {
        return [
            'host' => 'localhost',
            'dbname' => 'u454452574_ibyt_nivel',
            'username' => 'root',
            'password' => '',
            'port' => '3306'
        ];
    }
}

/**
 * Obter configurações JWT baseado no ambiente
 */
function getJWTConfig() {
    if (isProduction()) {
        return [
            'secret' => PROD_JWT_SECRET,
            'access_expire' => PROD_JWT_ACCESS_EXPIRE,
            'refresh_expire' => PROD_JWT_REFRESH_EXPIRE
        ];
    } else {
        return [
            'secret' => 'dev_jwt_secret_key_not_secure',
            'access_expire' => 3600,
            'refresh_expire' => 604800
        ];
    }
}

// ======================
// INICIALIZAÇÃO AUTOMÁTICA
// ======================

// Configurar ambiente automaticamente quando o arquivo é incluído
configureErrorReporting();
setSecurityHeaders();
configureCORS();

// Responder a requisições OPTIONS para CORS
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

?>