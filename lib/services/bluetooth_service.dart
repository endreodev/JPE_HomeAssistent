import 'dart:async';
import 'dart:convert';
import 'dart:io';
import 'package:flutter_blue_plus/flutter_blue_plus.dart' as fbp;
import 'package:permission_handler/permission_handler.dart';
import '../models/device.dart';
import '../models/wifi_credentials.dart';
import '../models/ble_constants.dart';
import 'encryption_service.dart';
import 'device_storage.dart';

/// Serviço principal para gerenciar comunicação Bluetooth Low Energy
class BluetoothService {
  static BluetoothService? _instance;
  final List<fbp.BluetoothDevice> _discoveredDevices = [];
  final Map<String, fbp.BluetoothDevice> _connectedDevices = {};
  final StreamController<List<Device>> _devicesController =
      StreamController.broadcast();
  final StreamController<String> _statusController =
      StreamController.broadcast();

  bool _isScanning = false;
  StreamSubscription<List<fbp.ScanResult>>? _scanSubscription;

  BluetoothService._();

  /// Singleton instance
  static BluetoothService get instance {
    _instance ??= BluetoothService._();
    return _instance!;
  }

  /// Stream de dispositivos descobertos
  Stream<List<Device>> get devicesStream => _devicesController.stream;

  /// Stream de status do Bluetooth
  Stream<String> get statusStream => _statusController.stream;

  /// Lista de dispositivos descobertos
  List<fbp.BluetoothDevice> get discoveredDevices =>
      List.from(_discoveredDevices);

  /// Verifica se o Bluetooth está ativo
  bool get isScanning => _isScanning;

  /// Verifica e solicita permissões necessárias
  Future<bool> requestPermissions() async {
    try {
      _updateStatus('Verificando permissões...');

      if (Platform.isAndroid) {
        // Android 12+ requer permissões específicas para BLE
        final bluetoothScanPermission = await Permission.bluetoothScan
            .request();
        final bluetoothConnectPermission = await Permission.bluetoothConnect
            .request();
        final bluetoothAdvertisePermission = await Permission.bluetoothAdvertise
            .request();
        final locationPermission = await Permission.locationWhenInUse.request();

        final allGranted =
            bluetoothScanPermission.isGranted &&
            bluetoothConnectPermission.isGranted &&
            bluetoothAdvertisePermission.isGranted &&
            locationPermission.isGranted;

        if (!allGranted) {
          _updateStatus('Permissões negadas');
          return false;
        }
      } else if (Platform.isIOS) {
        final bluetoothPermission = await Permission.bluetooth.request();
        final locationPermission = await Permission.locationWhenInUse.request();

        if (!bluetoothPermission.isGranted || !locationPermission.isGranted) {
          _updateStatus('Permissões negadas');
          return false;
        }
      }

      _updateStatus('Permissões concedidas');
      return true;
    } catch (e) {
      _updateStatus('Erro ao solicitar permissões: $e');
      return false;
    }
  }

  /// Verifica se o Bluetooth está disponível e ativo
  Future<bool> isBluetoothAvailable() async {
    try {
      if (await fbp.FlutterBluePlus.isAvailable) {
        final state = await fbp.FlutterBluePlus.adapterState.first;
        return state == fbp.BluetoothAdapterState.on;
      }
      return false;
    } catch (e) {
      print('Erro ao verificar Bluetooth: $e');
      return false;
    }
  }

  /// Ativa o Bluetooth (Android apenas)
  Future<bool> enableBluetooth() async {
    try {
      if (Platform.isAndroid) {
        _updateStatus('Ativando Bluetooth...');
        await fbp.FlutterBluePlus.turnOn();
        return true;
      }
      return await isBluetoothAvailable();
    } catch (e) {
      _updateStatus('Erro ao ativar Bluetooth: $e');
      return false;
    }
  }

  /// Inicia o escaneamento de dispositivos BLE
  Future<bool> startScan({Duration? timeout}) async {
    try {
      if (_isScanning) {
        print('Escaneamento já em andamento');
        return false;
      }

      // Verifica permissões e Bluetooth
      if (!await requestPermissions()) {
        return false;
      }

      if (!await isBluetoothAvailable()) {
        if (!await enableBluetooth()) {
          _updateStatus('Bluetooth não disponível');
          return false;
        }
      }

      _updateStatus('Iniciando escaneamento...');
      _isScanning = true;
      _discoveredDevices.clear();

      // Inicia escaneamento
      await fbp.FlutterBluePlus.startScan(
        withServices: [fbp.Guid(BleConstants.mainServiceUuid)],
        timeout:
            timeout ?? const Duration(seconds: BleConstants.scanTimeoutSeconds),
        androidUsesFineLocation: true,
      );

      // Escuta resultados do escaneamento
      _scanSubscription = fbp.FlutterBluePlus.scanResults.listen(
        (results) {
          _processScanResults(results);
        },
        onError: (error) {
          print('Erro no escaneamento: $error');
          _updateStatus('Erro no escaneamento: $error');
        },
      );

      // Para o escaneamento automaticamente após timeout
      if (timeout != null) {
        Timer(timeout, () {
          stopScan();
        });
      }

      _updateStatus('Escaneando dispositivos...');
      return true;
    } catch (e) {
      _updateStatus('Erro ao iniciar escaneamento: $e');
      _isScanning = false;
      return false;
    }
  }

  /// Para o escaneamento de dispositivos
  Future<void> stopScan() async {
    try {
      if (_isScanning) {
        await fbp.FlutterBluePlus.stopScan();
        await _scanSubscription?.cancel();
        _scanSubscription = null;
        _isScanning = false;
        _updateStatus('Escaneamento finalizado');

        // Salva timestamp do último scan
        await DeviceStorage.instance.saveLastScanTimestamp();
      }
    } catch (e) {
      print('Erro ao parar escaneamento: $e');
    }
  }

  /// Processa os resultados do escaneamento
  void _processScanResults(List<fbp.ScanResult> results) {
    final List<Device> devices = [];

    for (final result in results) {
      final device = result.device;

      // Filtra dispositivos IoT baseado no nome ou serviços
      if (_isIoTDevice(device, result.advertisementData)) {
        // Adiciona à lista se não estiver já presente
        if (!_discoveredDevices.any((d) => d.remoteId == device.remoteId)) {
          _discoveredDevices.add(device);
        }

        // Converte para modelo Device
        final deviceModel = Device(
          id: device.remoteId.toString(),
          name: device.platformName.isNotEmpty
              ? device.platformName
              : 'Dispositivo IoT',
          macAddress: device.remoteId.toString(),
          rssi: result.rssi,
          isConnected: device.isConnected,
        );

        devices.add(deviceModel);
      }
    }

    // Notifica listeners sobre novos dispositivos
    _devicesController.add(devices);
    _updateStatus('${devices.length} dispositivos encontrados');
  }

  /// Verifica se um dispositivo é um dispositivo IoT válido
  bool _isIoTDevice(
    fbp.BluetoothDevice device,
    fbp.AdvertisementData advertisementData,
  ) {
    // Verifica se contém o serviço principal
    if (advertisementData.serviceUuids.contains(BleConstants.mainServiceUuid)) {
      return true;
    }

    // Verifica pelo nome do dispositivo
    if (device.platformName.contains(BleConstants.deviceNamePrefix)) {
      return true;
    }

    // Adicione outros critérios conforme necessário
    return false;
  }

  /// Conecta a um dispositivo específico
  Future<bool> connectToDevice(String deviceId) async {
    try {
      final device = _discoveredDevices.firstWhere(
        (d) => d.remoteId.toString() == deviceId,
        orElse: () => throw StateError('Dispositivo não encontrado'),
      );

      _updateStatus('Conectando ao dispositivo...');

      // Conecta ao dispositivo
      await device.connect(
        timeout: const Duration(seconds: BleConstants.bleTimeoutSeconds),
        autoConnect: false,
      );

      // Descobre serviços
      await device.discoverServices();

      _connectedDevices[deviceId] = device;

      // Atualiza status no armazenamento
      await DeviceStorage.instance.updateDeviceConnection(deviceId, true);

      _updateStatus('Conectado ao dispositivo');
      return true;
    } catch (e) {
      _updateStatus('Erro ao conectar: $e');
      return false;
    }
  }

  /// Desconecta de um dispositivo
  Future<void> disconnectFromDevice(String deviceId) async {
    try {
      final device = _connectedDevices[deviceId];
      if (device != null) {
        await device.disconnect();
        _connectedDevices.remove(deviceId);

        // Atualiza status no armazenamento
        await DeviceStorage.instance.updateDeviceConnection(deviceId, false);

        _updateStatus('Dispositivo desconectado');
      }
    } catch (e) {
      _updateStatus('Erro ao desconectar: $e');
    }
  }

  /// Envia credenciais Wi-Fi para um dispositivo
  Future<bool> sendWifiCredentials(
    String deviceId,
    WifiCredentials credentials,
  ) async {
    try {
      final device = _connectedDevices[deviceId];
      if (device == null) {
        _updateStatus('Dispositivo não conectado');
        return false;
      }

      _updateStatus('Enviando credenciais Wi-Fi...');

      // Encontra o serviço e característica corretos
      final services = await device.discoverServices();
      final targetService = services.firstWhere(
        (service) => service.uuid.toString() == BleConstants.mainServiceUuid,
        orElse: () => throw StateError('Serviço não encontrado'),
      );

      final wifiCharacteristic = targetService.characteristics.firstWhere(
        (char) =>
            char.uuid.toString() == BleConstants.wifiConfigCharacteristicUuid,
        orElse: () => throw StateError('Característica Wi-Fi não encontrada'),
      );

      // Criptografa as credenciais
      final encryptedData = EncryptionService.instance.encryptWifiCredentials(
        credentials.ssid,
        credentials.password,
      );

      // Envia dados criptografados
      await wifiCharacteristic.write(
        utf8.encode(encryptedData),
        withoutResponse: false,
      );

      // Aguarda confirmação do dispositivo
      final success = await _waitForDeviceResponse(targetService);

      if (success) {
        // Atualiza status no armazenamento
        await DeviceStorage.instance.updateDeviceWifiConfig(
          deviceId,
          credentials.ssid,
          DeviceStatus.configured,
        );

        _updateStatus('Credenciais enviadas com sucesso');

        // Desconecta após configuração bem-sucedida
        await disconnectFromDevice(deviceId);

        return true;
      } else {
        await DeviceStorage.instance.updateDeviceStatus(
          deviceId,
          DeviceStatus.error,
        );
        _updateStatus('Falha na configuração Wi-Fi');
        return false;
      }
    } catch (e) {
      _updateStatus('Erro ao enviar credenciais: $e');
      await DeviceStorage.instance.updateDeviceStatus(
        deviceId,
        DeviceStatus.error,
      );
      return false;
    }
  }

  /// Aguarda resposta do dispositivo após envio de credenciais
  Future<bool> _waitForDeviceResponse(fbp.BluetoothService service) async {
    try {
      final statusCharacteristic = service.characteristics.firstWhere(
        (char) => char.uuid.toString() == BleConstants.statusCharacteristicUuid,
        orElse: () =>
            throw StateError('Característica de status não encontrada'),
      );

      // Configura notificações para receber resposta
      await statusCharacteristic.setNotifyValue(true);

      // Aguarda resposta com timeout
      final completer = Completer<bool>();
      StreamSubscription? subscription;

      subscription = statusCharacteristic.lastValueStream.listen((value) {
        if (value.isNotEmpty) {
          final response = utf8.decode(value);
          final deviceResponse = response.toDeviceResponse();

          subscription?.cancel();
          completer.complete(deviceResponse == DeviceResponse.success);
        }
      });

      // Timeout para resposta
      Timer(const Duration(seconds: 30), () {
        if (!completer.isCompleted) {
          subscription?.cancel();
          completer.complete(false);
        }
      });

      return await completer.future;
    } catch (e) {
      print('Erro ao aguardar resposta do dispositivo: $e');
      return false;
    }
  }

  /// Atualiza o status e notifica listeners
  void _updateStatus(String status) {
    print('Bluetooth Status: $status');
    _statusController.add(status);
  }

  /// Limpa recursos
  void dispose() {
    _scanSubscription?.cancel();
    _devicesController.close();
    _statusController.close();

    // Desconecta todos os dispositivos
    for (final device in _connectedDevices.values) {
      device.disconnect();
    }
    _connectedDevices.clear();
  }
}
