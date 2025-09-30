/// Constantes para UUIDs de serviços e características BLE
class BleConstants {
  /// UUID do serviço principal para configuração IoT
  static const String mainServiceUuid = '12345678-1234-1234-1234-1234567890ab';

  /// UUID da característica para envio de credenciais Wi-Fi
  static const String wifiConfigCharacteristicUuid =
      '12345678-1234-1234-1234-1234567890ac';

  /// UUID da característica para receber status de configuração
  static const String statusCharacteristicUuid =
      '12345678-1234-1234-1234-1234567890ad';

  /// UUID da característica para receber notificações
  static const String notificationCharacteristicUuid =
      '12345678-1234-1234-1234-1234567890ae';

  /// Timeout para operações BLE (em segundos)
  static const int bleTimeoutSeconds = 30;

  /// Tempo limite para escaneamento (em segundos)
  static const int scanTimeoutSeconds = 10;

  /// Prefixo padrão para nomes de dispositivos IoT
  static const String deviceNamePrefix = 'IoT_Device';

  /// Tamanho máximo de dados por pacote BLE
  static const int maxBlePacketSize = 20;
}

/// Enumeração para tipos de resposta do dispositivo
enum DeviceResponse {
  /// Configuração bem-sucedida
  success,

  /// Erro na configuração
  error,

  /// Credenciais inválidas
  invalidCredentials,

  /// Timeout na resposta
  timeout,

  /// Resposta desconhecida
  unknown,
}

/// Extensão para converter String em DeviceResponse
extension DeviceResponseExtension on String {
  DeviceResponse toDeviceResponse() {
    switch (toLowerCase()) {
      case 'ok':
      case 'success':
        return DeviceResponse.success;
      case 'error':
        return DeviceResponse.error;
      case 'invalid':
      case 'invalid_credentials':
        return DeviceResponse.invalidCredentials;
      case 'timeout':
        return DeviceResponse.timeout;
      default:
        return DeviceResponse.unknown;
    }
  }
}
