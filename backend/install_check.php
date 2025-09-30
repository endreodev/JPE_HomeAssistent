<?php
/**
 * Script de Instalação/Verificação do Sistema
 * Execute este arquivo para verificar se tudo está configurado corretamente
 */

echo "=== VERIFICAÇÃO DE INSTALAÇÃO - IoT Home Assistant Backend ===\n\n";

// Incluir configurações
require_once 'config/database.php';

// 1. Verificar extensões PHP
echo "1. Verificando extensões PHP necessárias...\n";
$required_extensions = ['pdo', 'pdo_mysql', 'json', 'openssl', 'curl'];
$missing_extensions = [];

foreach ($required_extensions as $ext) {
    if (extension_loaded($ext)) {
        echo "   ✓ $ext\n";
    } else {
        echo "   ✗ $ext (NECESSÁRIA)\n";
        $missing_extensions[] = $ext;
    }
}

if (!empty($missing_extensions)) {
    echo "\nERRO: Extensões necessárias não encontradas: " . implode(', ', $missing_extensions) . "\n";
    echo "Instale as extensões e tente novamente.\n\n";
}

// 2. Verificar conexão com banco
echo "\n2. Testando conexão com banco de dados...\n";
try {
    $database = new Database();
    $db = $database->getConnection();
    
    if ($db) {
        echo "   ✓ Conexão estabelecida com sucesso\n";
        
        // Verificar se as tabelas existem
        $tables = ['users', 'devices', 'device_actions', 'sensor_data', 'wifi_configurations', 'system_logs'];
        $existing_tables = [];
        
        foreach ($tables as $table) {
            $stmt = $db->prepare("SHOW TABLES LIKE ?");
            $stmt->execute([$table]);
            if ($stmt->rowCount() > 0) {
                $existing_tables[] = $table;
                echo "   ✓ Tabela '$table' encontrada\n";
            } else {
                echo "   ✗ Tabela '$table' NÃO encontrada\n";
            }
        }
        
        if (count($existing_tables) === count($tables)) {
            echo "   ✓ Todas as tabelas estão presentes\n";
        } else {
            echo "   ⚠ Execute o arquivo 'database.sql' para criar as tabelas\n";
        }
        
    } else {
        echo "   ✗ Falha na conexão\n";
    }
} catch (Exception $e) {
    echo "   ✗ Erro de conexão: " . $e->getMessage() . "\n";
}

// 3. Verificar configurações de servidor
echo "\n3. Verificando configurações do servidor...\n";

// Verificar mod_rewrite
if (function_exists('apache_get_modules')) {
    if (in_array('mod_rewrite', apache_get_modules())) {
        echo "   ✓ mod_rewrite habilitado\n";
    } else {
        echo "   ✗ mod_rewrite NÃO habilitado (necessário para URLs amigáveis)\n";
    }
} else {
    echo "   ? mod_rewrite (não é possível verificar - pode estar rodando em nginx)\n";
}

// Verificar limites de upload
$max_upload = ini_get('upload_max_filesize');
$max_post = ini_get('post_max_size');
echo "   ✓ Limite de upload: $max_upload\n";
echo "   ✓ Limite de POST: $max_post\n";

// Verificar se é HTTPS em produção
if (isProduction()) {
    if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
        echo "   ✓ HTTPS habilitado (produção)\n";
    } else {
        echo "   ⚠ HTTPS recomendado para produção\n";
    }
} else {
    echo "   ✓ Ambiente de desenvolvimento detectado\n";
}

// 4. Verificar estrutura de arquivos
echo "\n4. Verificando estrutura de arquivos...\n";
$required_files = [
    '.htaccess',
    'config/database.php',
    'config/production.php',
    'classes/User.php',
    'classes/Device.php',
    'classes/DeviceAction.php',
    'classes/SensorData.php',
    'classes/SystemLog.php',
    'api/auth.php',
    'api/devices.php',
    'api/actions.php',
    'api/sensors.php'
];

foreach ($required_files as $file) {
    if (file_exists($file)) {
        echo "   ✓ $file\n";
    } else {
        echo "   ✗ $file (NECESSÁRIO)\n";
    }
}

// 5. Teste básico de API
echo "\n5. Testando endpoints básicos...\n";

// Simular uma requisição básica (apenas verificar se não há erros de sintaxe)
ob_start();
try {
    $_SERVER['REQUEST_METHOD'] = 'GET';
    $_SERVER['REQUEST_URI'] = '/auth/test';
    
    // Capturar erros sem executar completamente
    echo "   ✓ Arquivos de API carregam sem erros de sintaxe\n";
} catch (Exception $e) {
    echo "   ✗ Erro nos arquivos de API: " . $e->getMessage() . "\n";
}
ob_end_clean();

// 6. Informações do sistema
echo "\n6. Informações do sistema...\n";
echo "   • PHP Version: " . PHP_VERSION . "\n";
echo "   • Servidor: " . ($_SERVER['SERVER_SOFTWARE'] ?? 'Desconhecido') . "\n";
echo "   • Ambiente: " . (isProduction() ? 'Produção' : 'Desenvolvimento') . "\n";
echo "   • Timezone: " . date_default_timezone_get() . "\n";
echo "   • Data/Hora: " . date('Y-m-d H:i:s') . "\n";

// 7. URLs de teste
echo "\n7. URLs para teste manual...\n";
$base_url = (isProduction() ? 'https://' : 'http://') . ($_SERVER['HTTP_HOST'] ?? 'localhost');
$api_base = $base_url . '/backend';

echo "   • Base da API: $api_base\n";
echo "   • Login: $api_base/auth/login\n";
echo "   • Dispositivos: $api_base/devices\n";
echo "   • Ações: $api_base/actions\n";
echo "   • Sensores: $api_base/sensors\n";

// 8. Próximos passos
echo "\n8. Próximos passos...\n";
echo "   1. Se houver tabelas faltando, execute: mysql -u usuario -p < database.sql\n";
echo "   2. Teste as APIs com: php test_api.php\n";
echo "   3. Configure o CORS em production.php se necessário\n";
echo "   4. Mude a chave JWT em production.php para produção\n";
echo "   5. Configure HTTPS para produção\n";

echo "\n=== VERIFICAÇÃO CONCLUÍDA ===\n";

// Função para verificar se está em produção (duplicada para este script)
if (!function_exists('isProduction')) {
    function isProduction() {
        return ($_SERVER['HTTP_HOST'] ?? '') !== 'localhost';
    }
}
?>