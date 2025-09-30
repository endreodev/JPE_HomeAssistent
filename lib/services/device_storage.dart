import 'dart:convert';
import 'package:shared_preferences/shared_preferences.dart';
import '../models/device.dart';

/// Serviço para gerenciar o armazenamento persistente de dispositivos
class DeviceStorage {
  static const String _devicesKey = 'saved_devices';
  static const String _lastScanKey = 'last_scan_timestamp';

  static DeviceStorage? _instance;
  SharedPreferences? _prefs;

  DeviceStorage._();

  /// Singleton instance
  static DeviceStorage get instance {
    _instance ??= DeviceStorage._();
    return _instance!;
  }

  /// Inicializa o SharedPreferences
  Future<void> init() async {
    _prefs ??= await SharedPreferences.getInstance();
  }

  /// Salva a lista de dispositivos no armazenamento local
  Future<bool> saveDevices(List<Device> devices) async {
    try {
      await init();
      final devicesJson = devices.map((device) => device.toJson()).toList();
      final devicesString = jsonEncode(devicesJson);
      return await _prefs!.setString(_devicesKey, devicesString);
    } catch (e) {
      print('Erro ao salvar dispositivos: $e');
      return false;
    }
  }

  /// Carrega a lista de dispositivos do armazenamento local
  Future<List<Device>> loadDevices() async {
    try {
      await init();
      final devicesString = _prefs!.getString(_devicesKey);

      if (devicesString == null || devicesString.isEmpty) {
        return [];
      }

      final List<dynamic> devicesJson = jsonDecode(devicesString);
      return devicesJson.map((json) => Device.fromJson(json)).toList();
    } catch (e) {
      print('Erro ao carregar dispositivos: $e');
      return [];
    }
  }

  /// Adiciona um novo dispositivo ou atualiza um existente
  Future<bool> saveDevice(Device device) async {
    try {
      final devices = await loadDevices();
      final existingIndex = devices.indexWhere((d) => d.id == device.id);

      if (existingIndex >= 0) {
        // Atualiza dispositivo existente
        devices[existingIndex] = device;
      } else {
        // Adiciona novo dispositivo
        devices.add(device);
      }

      return await saveDevices(devices);
    } catch (e) {
      print('Erro ao salvar dispositivo: $e');
      return false;
    }
  }

  /// Remove um dispositivo do armazenamento
  Future<bool> removeDevice(String deviceId) async {
    try {
      final devices = await loadDevices();
      devices.removeWhere((device) => device.id == deviceId);
      return await saveDevices(devices);
    } catch (e) {
      print('Erro ao remover dispositivo: $e');
      return false;
    }
  }

  /// Busca um dispositivo específico pelo ID
  Future<Device?> getDevice(String deviceId) async {
    try {
      final devices = await loadDevices();
      return devices.firstWhere(
        (device) => device.id == deviceId,
        orElse: () => throw StateError('Dispositivo não encontrado'),
      );
    } catch (e) {
      print('Dispositivo $deviceId não encontrado: $e');
      return null;
    }
  }

  /// Atualiza o status de um dispositivo específico
  Future<bool> updateDeviceStatus(
    String deviceId,
    DeviceStatus newStatus,
  ) async {
    try {
      final device = await getDevice(deviceId);
      if (device == null) return false;

      final updatedDevice = device.copyWith(
        status: newStatus,
        lastConfigured: newStatus == DeviceStatus.configured
            ? DateTime.now()
            : device.lastConfigured,
      );

      return await saveDevice(updatedDevice);
    } catch (e) {
      print('Erro ao atualizar status do dispositivo: $e');
      return false;
    }
  }

  /// Atualiza as credenciais Wi-Fi configuradas em um dispositivo
  Future<bool> updateDeviceWifiConfig(
    String deviceId,
    String ssid,
    DeviceStatus status,
  ) async {
    try {
      final device = await getDevice(deviceId);
      if (device == null) return false;

      final updatedDevice = device.copyWith(
        lastConfiguredSSID: ssid,
        status: status,
        lastConfigured: DateTime.now(),
      );

      return await saveDevice(updatedDevice);
    } catch (e) {
      print('Erro ao atualizar configuração Wi-Fi: $e');
      return false;
    }
  }

  /// Atualiza o estado de conexão BLE de um dispositivo
  Future<bool> updateDeviceConnection(String deviceId, bool isConnected) async {
    try {
      final device = await getDevice(deviceId);
      if (device == null) return false;

      final updatedDevice = device.copyWith(isConnected: isConnected);
      return await saveDevice(updatedDevice);
    } catch (e) {
      print('Erro ao atualizar conexão do dispositivo: $e');
      return false;
    }
  }

  /// Limpa todos os dispositivos salvos
  Future<bool> clearAllDevices() async {
    try {
      await init();
      return await _prefs!.remove(_devicesKey);
    } catch (e) {
      print('Erro ao limpar dispositivos: $e');
      return false;
    }
  }

  /// Salva o timestamp do último escaneamento
  Future<bool> saveLastScanTimestamp() async {
    try {
      await init();
      return await _prefs!.setInt(
        _lastScanKey,
        DateTime.now().millisecondsSinceEpoch,
      );
    } catch (e) {
      print('Erro ao salvar timestamp do último scan: $e');
      return false;
    }
  }

  /// Recupera o timestamp do último escaneamento
  Future<DateTime?> getLastScanTimestamp() async {
    try {
      await init();
      final timestamp = _prefs!.getInt(_lastScanKey);
      return timestamp != null
          ? DateTime.fromMillisecondsSinceEpoch(timestamp)
          : null;
    } catch (e) {
      print('Erro ao recuperar timestamp do último scan: $e');
      return null;
    }
  }

  /// Verifica se um dispositivo já está salvo
  Future<bool> isDeviceSaved(String deviceId) async {
    try {
      final device = await getDevice(deviceId);
      return device != null;
    } catch (e) {
      return false;
    }
  }

  /// Obtém estatísticas dos dispositivos salvos
  Future<Map<String, int>> getDeviceStatistics() async {
    try {
      final devices = await loadDevices();
      final stats = <String, int>{
        'total': devices.length,
        'configured': devices
            .where((d) => d.status == DeviceStatus.configured)
            .length,
        'notConfigured': devices
            .where((d) => d.status == DeviceStatus.notConfigured)
            .length,
        'connected': devices.where((d) => d.isConnected).length,
      };
      return stats;
    } catch (e) {
      print('Erro ao obter estatísticas: $e');
      return {'total': 0, 'configured': 0, 'notConfigured': 0, 'connected': 0};
    }
  }
}
