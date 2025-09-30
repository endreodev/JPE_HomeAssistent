<?php
/**
 * Script de teste para as APIs do sistema
 * Execute este arquivo para testar as funcionalidades básicas
 */

// Configurações
$base_url = 'https://seudominio.com/backend'; // Ajuste para seu domínio
$test_user = [
    'username' => 'teste_' . time(),
    'email' => 'teste_' . time() . '@exemplo.com',
    'password' => 'senha123',
    'full_name' => 'Usuário de Teste'
];

echo "=== TESTE DAS APIs JPE HOME ASSISTANT ===\n\n";

// Função para fazer requisições HTTP
function makeRequest($url, $method = 'GET', $data = null, $headers = []) {
    $ch = curl_init();
    
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_HTTPHEADER => array_merge([
            'Content-Type: application/json'
        ], $headers),
        CURLOPT_SSL_VERIFYPEER => false
    ]);
    
    if ($data) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    }
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    return [
        'status' => $http_code,
        'data' => json_decode($response, true)
    ];
}

// Teste 1: Registrar usuário
echo "1. Testando registro de usuário...\n";
$response = makeRequest("$base_url/auth/register", 'POST', $test_user);
echo "Status: " . $response['status'] . "\n";
echo "Resposta: " . json_encode($response['data'], JSON_PRETTY_PRINT) . "\n\n";

if ($response['status'] !== 201) {
    die("Erro no registro de usuário. Verifique a configuração.\n");
}

$access_token = $response['data']['data']['tokens']['access_token'];
$refresh_token = $response['data']['data']['tokens']['refresh_token'];
$user_id = $response['data']['data']['user']['id'];

// Teste 2: Login
echo "2. Testando login...\n";
$login_data = [
    'login' => $test_user['username'],
    'password' => $test_user['password']
];
$response = makeRequest("$base_url/auth/login", 'POST', $login_data);
echo "Status: " . $response['status'] . "\n";
echo "Resposta: " . json_encode($response['data'], JSON_PRETTY_PRINT) . "\n\n";

// Teste 3: Obter perfil
echo "3. Testando obtenção de perfil...\n";
$response = makeRequest("$base_url/auth/me", 'GET', null, [
    "Authorization: Bearer $access_token"
]);
echo "Status: " . $response['status'] . "\n";
echo "Resposta: " . json_encode($response['data'], JSON_PRETTY_PRINT) . "\n\n";

// Teste 4: Criar dispositivo
echo "4. Testando criação de dispositivo...\n";
$device_data = [
    'name' => 'Sensor de Teste',
    'mac_address' => 'AA:BB:CC:DD:EE:' . sprintf('%02X', rand(0, 255)),
    'device_type' => 'sensor',
    'status' => 'online'
];
$response = makeRequest("$base_url/devices", 'POST', $device_data, [
    "Authorization: Bearer $access_token"
]);
echo "Status: " . $response['status'] . "\n";
echo "Resposta: " . json_encode($response['data'], JSON_PRETTY_PRINT) . "\n\n";

if ($response['status'] === 201) {
    $device_id = $response['data']['data']['device']['id'];
    
    // Teste 5: Listar dispositivos
    echo "5. Testando listagem de dispositivos...\n";
    $response = makeRequest("$base_url/devices", 'GET', null, [
        "Authorization: Bearer $access_token"
    ]);
    echo "Status: " . $response['status'] . "\n";
    echo "Resposta: " . json_encode($response['data'], JSON_PRETTY_PRINT) . "\n\n";
    
    // Teste 6: Criar ação para o dispositivo
    echo "6. Testando criação de ação...\n";
    $action_data = [
        'device_id' => $device_id,
        'action_type' => 'set_wifi',
        'action_data' => [
            'ssid' => 'MinhaRede',
            'password' => 'senha_wifi'
        ]
    ];
    $response = makeRequest("$base_url/actions", 'POST', $action_data, [
        "Authorization: Bearer $access_token"
    ]);
    echo "Status: " . $response['status'] . "\n";
    echo "Resposta: " . json_encode($response['data'], JSON_PRETTY_PRINT) . "\n\n";
    
    if ($response['status'] === 201) {
        $action_id = $response['data']['data']['action']['id'];
        
        // Teste 7: Atualizar status da ação
        echo "7. Testando atualização de status da ação...\n";
        $status_data = [
            'status' => 'completed',
            'response_data' => [
                'connected' => true,
                'ip' => '192.168.1.100'
            ]
        ];
        $response = makeRequest("$base_url/actions/$action_id", 'PUT', $status_data, [
            "Authorization: Bearer $access_token"
        ]);
        echo "Status: " . $response['status'] . "\n";
        echo "Resposta: " . json_encode($response['data'], JSON_PRETTY_PRINT) . "\n\n";
    }
    
    // Teste 8: Enviar dados de sensor
    echo "8. Testando envio de dados de sensor...\n";
    $sensor_data = [
        'device_id' => $device_id,
        'sensor_type' => 'temperature',
        'sensor_value' => 25.5,
        'unit' => '°C'
    ];
    $response = makeRequest("$base_url/sensors", 'POST', $sensor_data, [
        "Authorization: Bearer $access_token"
    ]);
    echo "Status: " . $response['status'] . "\n";
    echo "Resposta: " . json_encode($response['data'], JSON_PRETTY_PRINT) . "\n\n";
    
    // Teste 9: Enviar dados em lote
    echo "9. Testando envio de dados em lote...\n";
    $batch_data = [
        'batch' => [
            [
                'device_id' => $device_id,
                'sensor_type' => 'humidity',
                'sensor_value' => 60.0,
                'unit' => '%'
            ],
            [
                'device_id' => $device_id,
                'sensor_type' => 'pressure',
                'sensor_value' => 1013.25,
                'unit' => 'hPa'
            ]
        ]
    ];
    $response = makeRequest("$base_url/sensors", 'POST', $batch_data, [
        "Authorization: Bearer $access_token"
    ]);
    echo "Status: " . $response['status'] . "\n";
    echo "Resposta: " . json_encode($response['data'], JSON_PRETTY_PRINT) . "\n\n";
    
    // Teste 10: Obter dados do dispositivo
    echo "10. Testando obtenção de dados do dispositivo...\n";
    $response = makeRequest("$base_url/sensors/device/$device_id", 'GET', null, [
        "Authorization: Bearer $access_token"
    ]);
    echo "Status: " . $response['status'] . "\n";
    echo "Resposta: " . json_encode($response['data'], JSON_PRETTY_PRINT) . "\n\n";
}

// Teste 11: Renovar token
echo "11. Testando renovação de token...\n";
$refresh_data = ['refresh_token' => $refresh_token];
$response = makeRequest("$base_url/auth/refresh", 'POST', $refresh_data);
echo "Status: " . $response['status'] . "\n";
echo "Resposta: " . json_encode($response['data'], JSON_PRETTY_PRINT) . "\n\n";

// Teste 12: Logout
echo "12. Testando logout...\n";
$response = makeRequest("$base_url/auth/logout", 'POST', [], [
    "Authorization: Bearer $access_token"
]);
echo "Status: " . $response['status'] . "\n";
echo "Resposta: " . json_encode($response['data'], JSON_PRETTY_PRINT) . "\n\n";

echo "=== TESTES CONCLUÍDOS ===\n";

// Função para verificar conectividade
function checkConnectivity($base_url) {
    echo "\n=== VERIFICAÇÃO DE CONECTIVIDADE ===\n";
    
    // Teste de conectividade básica
    $ch = curl_init("$base_url/auth/login");
    curl_setopt($ch, CURLOPT_NOBODY, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    
    $result = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($result !== false && $http_code !== 0) {
        echo "✓ Servidor acessível (HTTP $http_code)\n";
        return true;
    } else {
        echo "✗ Servidor não acessível\n";
        echo "Verifique se:\n";
        echo "- O servidor web está rodando\n";
        echo "- A URL base está correta: $base_url\n";
        echo "- As configurações de CORS estão corretas\n";
        return false;
    }
}

checkConnectivity($base_url);
?>