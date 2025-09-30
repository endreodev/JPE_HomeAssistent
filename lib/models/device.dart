import 'package:json_annotation/json_annotation.dart';

part 'device.g.dart';

/// Enumeração para representar o status de configuração do dispositivo
enum DeviceStatus {
  /// Dispositivo não configurado (sem credenciais Wi-Fi)
  notConfigured,

  /// Dispositivo configurado (credenciais Wi-Fi enviadas)
  configured,

  /// Configuração em progresso
  configuring,

  /// Erro na configuração
  error,
}

/// Modelo de dados para representar um dispositivo IoT
@JsonSerializable()
class Device {
  /// ID único do dispositivo (MAC address)
  final String id;

  /// Nome amigável do dispositivo
  final String name;

  /// Endereço MAC do dispositivo Bluetooth
  final String macAddress;

  /// UUID do serviço BLE personalizado
  final String serviceUuid;

  /// Última SSID configurada no dispositivo
  final String? lastConfiguredSSID;

  /// Status atual da configuração
  final DeviceStatus status;

  /// Data da última configuração
  final DateTime? lastConfigured;

  /// RSSI (força do sinal) quando descoberto
  final int? rssi;

  /// Indica se o dispositivo está atualmente conectado via BLE
  final bool isConnected;

  const Device({
    required this.id,
    required this.name,
    required this.macAddress,
    this.serviceUuid = '12345678-1234-1234-1234-1234567890ab',
    this.lastConfiguredSSID,
    this.status = DeviceStatus.notConfigured,
    this.lastConfigured,
    this.rssi,
    this.isConnected = false,
  });

  /// Cria uma cópia do dispositivo com alguns campos atualizados
  Device copyWith({
    String? id,
    String? name,
    String? macAddress,
    String? serviceUuid,
    String? lastConfiguredSSID,
    DeviceStatus? status,
    DateTime? lastConfigured,
    int? rssi,
    bool? isConnected,
  }) {
    return Device(
      id: id ?? this.id,
      name: name ?? this.name,
      macAddress: macAddress ?? this.macAddress,
      serviceUuid: serviceUuid ?? this.serviceUuid,
      lastConfiguredSSID: lastConfiguredSSID ?? this.lastConfiguredSSID,
      status: status ?? this.status,
      lastConfigured: lastConfigured ?? this.lastConfigured,
      rssi: rssi ?? this.rssi,
      isConnected: isConnected ?? this.isConnected,
    );
  }

  /// Retorna uma descrição amigável do status
  String get statusDescription {
    switch (status) {
      case DeviceStatus.notConfigured:
        return 'Não configurado';
      case DeviceStatus.configured:
        return 'Configurado';
      case DeviceStatus.configuring:
        return 'Configurando...';
      case DeviceStatus.error:
        return 'Erro na configuração';
    }
  }

  /// Retorna o ícone apropriado para o status
  String get statusIcon {
    switch (status) {
      case DeviceStatus.notConfigured:
        return '❌';
      case DeviceStatus.configured:
        return '✅';
      case DeviceStatus.configuring:
        return '⏳';
      case DeviceStatus.error:
        return '⚠️';
    }
  }

  /// Factory constructor para criar Device a partir de JSON
  factory Device.fromJson(Map<String, dynamic> json) => _$DeviceFromJson(json);

  /// Converte Device para JSON
  Map<String, dynamic> toJson() => _$DeviceToJson(this);

  @override
  bool operator ==(Object other) =>
      identical(this, other) ||
      other is Device && runtimeType == other.runtimeType && id == other.id;

  @override
  int get hashCode => id.hashCode;

  @override
  String toString() {
    return 'Device{id: $id, name: $name, status: $status, isConnected: $isConnected}';
  }
}

/// Extensão para converter String em DeviceStatus
extension DeviceStatusExtension on String {
  DeviceStatus toDeviceStatus() {
    switch (toLowerCase()) {
      case 'notconfigured':
        return DeviceStatus.notConfigured;
      case 'configured':
        return DeviceStatus.configured;
      case 'configuring':
        return DeviceStatus.configuring;
      case 'error':
        return DeviceStatus.error;
      default:
        return DeviceStatus.notConfigured;
    }
  }
}
