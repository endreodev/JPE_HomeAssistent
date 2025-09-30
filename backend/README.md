# API Backend - JPE Home Assistant

Backend PHP para aplicativo Flutter de gerenciamento de dispositivos IoT via BLE.

## 📋 Funcionalidades

- **Autenticação JWT**: Login/registro seguro com tokens de acesso e refresh
- **Gerenciamento de Dispositivos**: CRUD completo para dispositivos IoT
- **Ações de Dispositivos**: Sistema de comandos e controle remoto
- **Dados de Sensores**: Coleta e armazenamento de dados de sensores em tempo real
- **Sistema de Logs**: Auditoria completa de ações do sistema
- **Configurações Wi-Fi**: Gerenciamento de configurações de rede dos dispositivos

## 🚀 Configuração

### Pré-requisitos

- PHP 7.4+ com extensões:
  - PDO
  - MySQL/MySQLi
  - JSON
  - OpenSSL
- MySQL 5.7+ ou MariaDB 10.2+
- Apache com mod_rewrite habilitado

### Instalação

1. **Clone o repositório**
```bash
git clone <repository-url>
cd jpehomeassistente/backend
```

2. **Configure o banco de dados**
```bash
mysql -u root -p < database.sql
```

3. **Configure as credenciais**
As configurações estão em `config/production.php`. O sistema detecta automaticamente se está em produção ou desenvolvimento:

**Produção (automático quando host ≠ localhost):**
- Host: 62.72.62.1
- Database: u454452574_ibyt_nivel
- Username: u454452574_ibyt_nivel
- Password: Terra@15700

**Desenvolvimento (localhost):**
- Host: localhost
- Database: u454452574_ibyt_nivel
- Username: root
- Password: (vazio)

4. **Configure o servidor web**
- Aponte o DocumentRoot para a pasta `backend/`
- Certifique-se que o mod_rewrite está habilitado
- O arquivo `.htaccess` já está configurado

## 📚 Documentação da API

### Base URL
```
http://localhost/backend/
```

### Autenticação

#### Registrar usuário
```http
POST /auth/register
Content-Type: application/json

{
  "username": "usuario",
  "email": "email@exemplo.com",
  "password": "senha123",
  "full_name": "Nome Completo"
}
```

#### Login
```http
POST /auth/login
Content-Type: application/json

{
  "login": "usuario",
  "password": "senha123"
}
```

#### Renovar token
```http
POST /auth/refresh
Content-Type: application/json

{
  "refresh_token": "token_refresh_aqui"
}
```

#### Obter perfil
```http
GET /auth/me
Authorization: Bearer token_acesso_aqui
```

### Dispositivos

#### Listar dispositivos
```http
GET /devices
Authorization: Bearer token_acesso_aqui
```

#### Obter dispositivo específico
```http
GET /devices/{id}
Authorization: Bearer token_acesso_aqui
```

#### Criar dispositivo
```http
POST /devices
Authorization: Bearer token_acesso_aqui
Content-Type: application/json

{
  "name": "Sensor Temperatura",
  "mac_address": "AA:BB:CC:DD:EE:FF",
  "device_type": "sensor",
  "status": "online"
}
```

#### Atualizar dispositivo
```http
PUT /devices/{id}
Authorization: Bearer token_acesso_aqui
Content-Type: application/json

{
  "name": "Novo Nome",
  "status": "offline"
}
```

#### Deletar dispositivo
```http
DELETE /devices/{id}
Authorization: Bearer token_acesso_aqui
```

### Ações de Dispositivos

#### Listar ações pendentes
```http
GET /actions
Authorization: Bearer token_acesso_aqui
```

#### Obter ações de um dispositivo
```http
GET /actions/device/{device_id}
Authorization: Bearer token_acesso_aqui
```

#### Criar ação
```http
POST /actions
Authorization: Bearer token_acesso_aqui
Content-Type: application/json

{
  "device_id": 1,
  "action_type": "set_wifi",
  "action_data": {
    "ssid": "MinhaRede",
    "password": "senha_wifi"
  }
}
```

#### Atualizar status da ação
```http
PUT /actions/{id}
Authorization: Bearer token_acesso_aqui
Content-Type: application/json

{
  "status": "completed",
  "response_data": {
    "connected": true,
    "ip": "192.168.1.100"
  }
}
```

### Dados de Sensores

#### Obter dados do usuário
```http
GET /sensors?sensor_type=temperature&limit=50
Authorization: Bearer token_acesso_aqui
```

#### Obter dados de um dispositivo
```http
GET /sensors/device/{device_id}?from_date=2024-01-01&to_date=2024-01-31
Authorization: Bearer token_acesso_aqui
```

#### Obter último valor de um sensor
```http
GET /sensors/latest/{device_id}/{sensor_type}
Authorization: Bearer token_acesso_aqui
```

#### Salvar dados de sensor
```http
POST /sensors
Authorization: Bearer token_acesso_aqui
Content-Type: application/json

{
  "device_id": 1,
  "sensor_type": "temperature",
  "sensor_value": 25.5,
  "unit": "°C"
}
```

#### Salvar dados em lote
```http
POST /sensors
Authorization: Bearer token_acesso_aqui
Content-Type: application/json

{
  "batch": [
    {
      "device_id": 1,
      "sensor_type": "temperature",
      "sensor_value": 25.5,
      "unit": "°C"
    },
    {
      "device_id": 1,
      "sensor_type": "humidity",
      "sensor_value": 60.0,
      "unit": "%"
    }
  ]
}
```

## 🔒 Segurança

### Autenticação JWT
- Tokens de acesso com expiração de 1 hora
- Tokens de refresh com expiração de 7 dias
- Algoritmo HS256 para assinatura

### Validação de Dados
- Sanitização de entrada para prevenir SQL Injection
- Validação de tipos de dados
- Escape de caracteres especiais

### Logs de Auditoria
- Log de todas as ações importantes
- Rastreamento de IP e User-Agent
- Histórico de login/logout

## 📊 Estrutura do Banco de Dados

### Tabelas Principais

- **users**: Usuários do sistema
- **devices**: Dispositivos IoT registrados
- **device_actions**: Comandos e ações para dispositivos
- **sensor_data**: Dados coletados dos sensores
- **wifi_configurations**: Configurações Wi-Fi dos dispositivos
- **system_logs**: Logs de auditoria do sistema

### Relacionamentos

```
users (1:N) devices
devices (1:N) device_actions
devices (1:N) sensor_data
devices (1:N) wifi_configurations
users (1:N) system_logs
```

## 🛠️ Desenvolvimento

### Estrutura de Arquivos
```
backend/
├── .htaccess                 # Configuração Apache
├── database.sql             # Schema do banco
├── config/
│   └── database.php         # Configuração do banco
├── classes/
│   ├── User.php            # Classe de usuários
│   ├── Device.php          # Classe de dispositivos
│   ├── DeviceAction.php    # Classe de ações
│   ├── SensorData.php      # Classe de dados de sensores
│   └── SystemLog.php       # Classe de logs
└── api/
    ├── auth.php            # Endpoints de autenticação
    ├── devices.php         # Endpoints de dispositivos
    ├── actions.php         # Endpoints de ações
    └── sensors.php         # Endpoints de sensores
```

### Padrões de Código
- PSR-4 para autoload (futuro)
- Comentários em português
- Validação rigorosa de entrada
- Respostas JSON padronizadas

### Resposta Padrão da API
```json
{
  "success": true,
  "message": "Operação realizada com sucesso",
  "data": {...},
  "timestamp": "2024-01-15 10:30:00"
}
```

## 🧪 Testes

### Teste Manual com cURL

**Login:**
```bash
curl -X POST http://localhost/backend/auth/login \
  -H "Content-Type: application/json" \
  -d '{"login":"usuario","password":"senha123"}'
```

**Listar dispositivos:**
```bash
curl -X GET http://localhost/backend/devices \
  -H "Authorization: Bearer SEU_TOKEN_AQUI"
```

### Códigos de Status HTTP
- `200` - Sucesso
- `201` - Criado com sucesso
- `400` - Erro de validação
- `401` - Não autorizado
- `403` - Proibido
- `404` - Não encontrado
- `500` - Erro interno do servidor

## 📝 Logs

### Tipos de Logs Registrados
- `login_success` / `login_failed`
- `logout`
- `device_create` / `device_update` / `device_delete`
- `device_action`
- `user_update`

### Limpeza Automática
- Dados de sensores: 90 dias (configurável)
- Ações de dispositivos: 30 dias (configurável)
- Logs do sistema: 365 dias (configurável)

## 🔧 Manutenção

### Comandos Úteis

**Limpeza de dados antigos:**
```sql
-- Executar periodicamente via cron
CALL CleanOldData();
```

**Backup do banco:**
```bash
mysqldump -u usuario -p jpe_home_assistant > backup_$(date +%Y%m%d).sql
```

## 📞 Suporte

Para dúvidas sobre a implementação ou uso da API, consulte:
- Documentação inline no código
- Exemplos de uso nos comentários
- Logs de erro no sistema

## 🔄 Integração com Flutter

Esta API foi desenvolvida especificamente para integração com o aplicativo Flutter JPE Home Assistant. Os endpoints foram projetados para suportar:

- Configuração de dispositivos BLE
- Transmissão segura de credenciais Wi-Fi
- Monitoramento em tempo real de sensores
- Controle remoto de dispositivos IoT
- Sincronização de dados offline/online