#include "BLEDevice.h"
#include "BLEServer.h"
#include "BLEUtils.h"
#include "BLE2902.h"
#include <WiFi.h>
#include <ArduinoJson.h>

// UUIDs devem coincidir com os do app Flutter
#define SERVICE_UUID        "12345678-1234-1234-1234-1234567890ab"
#define WIFI_CHAR_UUID      "12345678-1234-1234-1234-1234567890ac"
#define STATUS_CHAR_UUID    "12345678-1234-1234-1234-1234567890ad"
#define NOTIFY_CHAR_UUID    "12345678-1234-1234-1234-1234567890ae"

// Nome do dispositivo BLE
#define DEVICE_NAME "IoT_Device_01"

BLEServer* pServer = NULL;
BLECharacteristic* pWifiCharacteristic = NULL;
BLECharacteristic* pStatusCharacteristic = NULL;
BLECharacteristic* pNotifyCharacteristic = NULL;
bool deviceConnected = false;
bool wifiConfigured = false;

// Estrutura para armazenar credenciais Wi-Fi
struct WiFiCredentials {
  String ssid;
  String password;
};

class MyServerCallbacks: public BLEServerCallbacks {
    void onConnect(BLEServer* pServer) {
      deviceConnected = true;
      Serial.println("Cliente BLE conectado");
    }

    void onDisconnect(BLEServer* pServer) {
      deviceConnected = false;
      Serial.println("Cliente BLE desconectado");
      
      // Reinicia advertising para permitir nova conexão
      delay(500); 
      pServer->getAdvertising()->start();
      Serial.println("Reiniciando advertising...");
    }
};

class WiFiCallbacks: public BLECharacteristicCallbacks {
    void onWrite(BLECharacteristic *pCharacteristic) {
      String receivedValue = pCharacteristic->getValue().c_str();
      
      if (receivedValue.length() > 0) {
        Serial.println("Dados recebidos via BLE: " + receivedValue);
        
        // Aqui você deve implementar a descriptografia AES
        // Por simplicidade, este exemplo assume dados em texto plano
        // Formato esperado: "SSID:PASSWORD"
        
        WiFiCredentials creds = parseCredentials(receivedValue);
        
        if (creds.ssid.length() > 0 && creds.password.length() > 0) {
          connectToWiFi(creds);
        } else {
          sendStatusResponse("ERROR");
        }
      }
    }
};

WiFiCredentials parseCredentials(String data) {
  WiFiCredentials creds;
  
  // Parse simples para "SSID:PASSWORD"
  int separatorIndex = data.indexOf(':');
  if (separatorIndex > 0) {
    creds.ssid = data.substring(0, separatorIndex);
    creds.password = data.substring(separatorIndex + 1);
  }
  
  return creds;
}

void connectToWiFi(WiFiCredentials creds) {
  Serial.println("Tentando conectar ao Wi-Fi...");
  Serial.println("SSID: " + creds.ssid);
  
  WiFi.begin(creds.ssid.c_str(), creds.password.c_str());
  
  // Timeout de 30 segundos para conexão
  unsigned long startTime = millis();
  while (WiFi.status() != WL_CONNECTED && millis() - startTime < 30000) {
    delay(1000);
    Serial.print(".");
  }
  
  if (WiFi.status() == WL_CONNECTED) {
    Serial.println("");
    Serial.println("WiFi conectado!");
    Serial.print("Endereço IP: ");
    Serial.println(WiFi.localIP());
    
    wifiConfigured = true;
    sendStatusResponse("OK");
    
    // Opcional: desabilitar BLE após configuração bem-sucedida
    // para economizar energia
    disableBLE();
    
  } else {
    Serial.println("");
    Serial.println("Falha na conexão WiFi");
    sendStatusResponse("ERROR");
  }
}

void sendStatusResponse(String status) {
  if (pStatusCharacteristic && deviceConnected) {
    pStatusCharacteristic->setValue(status.c_str());
    pStatusCharacteristic->notify();
    
    Serial.println("Status enviado: " + status);
  }
}

void disableBLE() {
  if (pServer) {
    pServer->getAdvertising()->stop();
    Serial.println("BLE desabilitado para economizar energia");
  }
}

void setup() {
  Serial.begin(115200);
  Serial.println("Iniciando dispositivo IoT...");
  
  setupBLE();
  
  Serial.println("Dispositivo pronto! Aguardando configuração via BLE.");
}

void setupBLE() {
  // Inicializar BLE
  BLEDevice::init(DEVICE_NAME);
  
  // Criar servidor BLE
  pServer = BLEDevice::createServer();
  pServer->setCallbacks(new MyServerCallbacks());
  
  // Criar serviço
  BLEService *pService = pServer->createService(SERVICE_UUID);
  
  // Característica para receber credenciais Wi-Fi
  pWifiCharacteristic = pService->createCharacteristic(
                         WIFI_CHAR_UUID,
                         BLECharacteristic::PROPERTY_WRITE
                       );
  pWifiCharacteristic->setCallbacks(new WiFiCallbacks());
  
  // Característica para enviar status (com notificação)
  pStatusCharacteristic = pService->createCharacteristic(
                           STATUS_CHAR_UUID,
                           BLECharacteristic::PROPERTY_READ   |
                           BLECharacteristic::PROPERTY_NOTIFY
                         );
  
  // Adicionar descritor para notificações
  pStatusCharacteristic->addDescriptor(new BLE2902());
  
  // Iniciar serviço
  pService->start();
  
  // Configurar advertising
  BLEAdvertising *pAdvertising = BLEDevice::getAdvertising();
  pAdvertising->addServiceUUID(SERVICE_UUID);
  pAdvertising->setScanResponse(false);
  pAdvertising->setMinPreferred(0x0);  // set value to 0x00 to not advertise this parameter
  
  // Iniciar advertising
  BLEDevice::startAdvertising();
  Serial.println("Advertising BLE iniciado!");
}

void loop() {
  // Se Wi-Fi estiver configurado, você pode executar sua lógica principal aqui
  if (wifiConfigured) {
    // Exemplo: enviar dados para servidor, ler sensores, etc.
    
    // Verifica se a conexão Wi-Fi ainda está ativa
    if (WiFi.status() != WL_CONNECTED) {
      Serial.println("Conexão Wi-Fi perdida. Reiniciando BLE...");
      wifiConfigured = false;
      setupBLE();
    }
  }
  
  // Pequeno delay para não sobrecarregar o loop
  delay(1000);
  
  // Opcional: piscar LED para indicar status
  static bool ledState = false;
  ledState = !ledState;
  digitalWrite(LED_BUILTIN, ledState ? HIGH : LOW);
}

// Função opcional para reset de configuração
void resetConfiguration() {
  Serial.println("Resetando configuração...");
  WiFi.disconnect();
  wifiConfigured = false;
  setupBLE();
}

// Exemplo de como implementar criptografia AES (básica)
// Nota: Para segurança real, use bibliotecas de criptografia adequadas
String decryptData(String encryptedData) {
  // Implementar descriptografia AES aqui
  // Por enquanto, retorna os dados sem modificação
  return encryptedData;
}