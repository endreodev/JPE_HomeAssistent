import 'package:flutter/material.dart';
import 'dart:async';
import '../models/device.dart';
import '../services/bluetooth_service.dart';
import '../services/device_storage.dart';

/// Tela para escanear e descobrir dispositivos BLE
class ScanScreen extends StatefulWidget {
  const ScanScreen({super.key});

  @override
  State<ScanScreen> createState() => _ScanScreenState();
}

class _ScanScreenState extends State<ScanScreen> {
  final BluetoothService _bluetoothService = BluetoothService.instance;
  List<Device> _discoveredDevices = [];
  List<Device> _savedDevices = [];
  String _statusMessage = 'Pronto para escanear';
  bool _isScanning = false;
  StreamSubscription? _devicesSubscription;
  StreamSubscription? _statusSubscription;
  String _searchFilter = '';
  bool _showOnlyNewDevices = false;

  @override
  void initState() {
    super.initState();
    _loadSavedDevices();
    _setupStreams();
  }

  @override
  void dispose() {
    _devicesSubscription?.cancel();
    _statusSubscription?.cancel();
    if (_isScanning) {
      _bluetoothService.stopScan();
    }
    super.dispose();
  }

  /// Carrega dispositivos já salvos
  Future<void> _loadSavedDevices() async {
    try {
      final devices = await DeviceStorage.instance.loadDevices();
      setState(() => _savedDevices = devices);
    } catch (e) {
      print('Erro ao carregar dispositivos salvos: $e');
    }
  }

  /// Configura streams para escutar mudanças
  void _setupStreams() {
    _devicesSubscription = _bluetoothService.devicesStream.listen((devices) {
      if (mounted) {
        setState(() => _discoveredDevices = devices);
      }
    });

    _statusSubscription = _bluetoothService.statusStream.listen((status) {
      if (mounted) {
        setState(() => _statusMessage = status);
      }
    });
  }

  /// Inicia o escaneamento de dispositivos
  Future<void> _startScan() async {
    setState(() {
      _isScanning = true;
      _discoveredDevices.clear();
    });

    final success = await _bluetoothService.startScan(
      timeout: const Duration(seconds: 30),
    );

    if (!success && mounted) {
      setState(() => _isScanning = false);
      _showErrorSnackBar('Falha ao iniciar escaneamento');
    }

    // Para automaticamente após 30 segundos
    Timer(const Duration(seconds: 30), () {
      if (_isScanning && mounted) {
        _stopScan();
      }
    });
  }

  /// Para o escaneamento
  Future<void> _stopScan() async {
    await _bluetoothService.stopScan();
    if (mounted) {
      setState(() => _isScanning = false);
    }
  }

  /// Adiciona um dispositivo à lista de salvos
  Future<void> _addDevice(Device device) async {
    try {
      final success = await DeviceStorage.instance.saveDevice(device);
      if (success) {
        await _loadSavedDevices();
        _showSuccessSnackBar('Dispositivo adicionado com sucesso');
      } else {
        _showErrorSnackBar('Falha ao adicionar dispositivo');
      }
    } catch (e) {
      _showErrorSnackBar('Erro ao adicionar dispositivo: $e');
    }
  }

  /// Verifica se um dispositivo já está salvo
  bool _isDeviceSaved(Device device) {
    return _savedDevices.any((d) => d.id == device.id);
  }

  /// Filtra dispositivos baseado no filtro de busca
  List<Device> _getFilteredDevices() {
    List<Device> devices = List.from(_discoveredDevices);

    // Filtro por nome
    if (_searchFilter.isNotEmpty) {
      devices = devices
          .where(
            (device) =>
                device.name.toLowerCase().contains(
                  _searchFilter.toLowerCase(),
                ) ||
                device.macAddress.toLowerCase().contains(
                  _searchFilter.toLowerCase(),
                ),
          )
          .toList();
    }

    // Filtro para mostrar apenas novos dispositivos
    if (_showOnlyNewDevices) {
      devices = devices.where((device) => !_isDeviceSaved(device)).toList();
    }

    // Ordena por RSSI (força do sinal)
    devices.sort((a, b) {
      final rssiA = a.rssi ?? -100;
      final rssiB = b.rssi ?? -100;
      return rssiB.compareTo(rssiA);
    });

    return devices;
  }

  @override
  Widget build(BuildContext context) {
    final filteredDevices = _getFilteredDevices();

    return Scaffold(
      appBar: AppBar(
        title: const Text('Escanear Dispositivos'),
        backgroundColor: Theme.of(context).colorScheme.inversePrimary,
        actions: [
          IconButton(
            icon: Icon(_isScanning ? Icons.stop : Icons.search),
            onPressed: _isScanning ? _stopScan : _startScan,
            tooltip: _isScanning
                ? 'Parar escaneamento'
                : 'Iniciar escaneamento',
          ),
        ],
      ),
      body: Column(
        children: [
          // Card de status
          _buildStatusCard(),

          // Filtros
          _buildFiltersSection(),

          // Lista de dispositivos
          Expanded(child: _buildDevicesList(filteredDevices)),
        ],
      ),
      floatingActionButton: _isScanning
          ? FloatingActionButton(
              onPressed: _stopScan,
              backgroundColor: Colors.red,
              child: const Icon(Icons.stop),
            )
          : FloatingActionButton(
              onPressed: _startScan,
              child: const Icon(Icons.search),
            ),
    );
  }

  /// Card com status atual do escaneamento
  Widget _buildStatusCard() {
    return Card(
      margin: const EdgeInsets.all(16),
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Row(
          children: [
            if (_isScanning)
              const SizedBox(
                width: 20,
                height: 20,
                child: CircularProgressIndicator(strokeWidth: 2),
              )
            else
              Icon(
                Icons.bluetooth_searching,
                color: Theme.of(context).primaryColor,
              ),
            const SizedBox(width: 12),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    _isScanning ? 'Escaneando...' : 'Escaneamento parado',
                    style: const TextStyle(
                      fontSize: 16,
                      fontWeight: FontWeight.bold,
                    ),
                  ),
                  Text(_statusMessage, style: const TextStyle(fontSize: 14)),
                  if (_discoveredDevices.isNotEmpty)
                    Text(
                      '${_discoveredDevices.length} dispositivos encontrados',
                      style: TextStyle(fontSize: 12, color: Colors.grey[600]),
                    ),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }

  /// Seção de filtros
  Widget _buildFiltersSection() {
    return Card(
      margin: const EdgeInsets.symmetric(horizontal: 16),
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          children: [
            // Campo de busca
            TextField(
              decoration: const InputDecoration(
                hintText: 'Buscar por nome ou MAC...',
                prefixIcon: Icon(Icons.search),
                border: OutlineInputBorder(),
              ),
              onChanged: (value) {
                setState(() => _searchFilter = value);
              },
            ),
            const SizedBox(height: 12),

            // Checkbox para mostrar apenas novos dispositivos
            Row(
              children: [
                Checkbox(
                  value: _showOnlyNewDevices,
                  onChanged: (value) {
                    setState(() => _showOnlyNewDevices = value ?? false);
                  },
                ),
                const Text('Mostrar apenas dispositivos novos'),
              ],
            ),
          ],
        ),
      ),
    );
  }

  /// Lista de dispositivos descobertos
  Widget _buildDevicesList(List<Device> devices) {
    if (!_isScanning && devices.isEmpty) {
      return _buildEmptyState();
    }

    return ListView.builder(
      padding: const EdgeInsets.all(16),
      itemCount: devices.length,
      itemBuilder: (context, index) {
        final device = devices[index];
        return _buildDeviceCard(device);
      },
    );
  }

  /// Estado vazio
  Widget _buildEmptyState() {
    return Center(
      child: Column(
        mainAxisAlignment: MainAxisAlignment.center,
        children: [
          Icon(Icons.bluetooth_disabled, size: 80, color: Colors.grey[400]),
          const SizedBox(height: 16),
          Text(
            'Nenhum dispositivo encontrado',
            style: TextStyle(
              fontSize: 18,
              color: Colors.grey[600],
              fontWeight: FontWeight.w500,
            ),
          ),
          const SizedBox(height: 8),
          Text(
            _isScanning
                ? 'Escaneando...'
                : 'Toque no botão + para iniciar escaneamento',
            style: TextStyle(fontSize: 14, color: Colors.grey[500]),
          ),
        ],
      ),
    );
  }

  /// Card de dispositivo individual
  Widget _buildDeviceCard(Device device) {
    final isAlreadySaved = _isDeviceSaved(device);

    return Card(
      margin: const EdgeInsets.only(bottom: 12),
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Row(
          children: [
            // Ícone do dispositivo
            Container(
              padding: const EdgeInsets.all(12),
              decoration: BoxDecoration(
                color: Theme.of(context).primaryColor.withOpacity(0.1),
                borderRadius: BorderRadius.circular(8),
              ),
              child: Icon(
                Icons.devices_other,
                color: Theme.of(context).primaryColor,
                size: 24,
              ),
            ),
            const SizedBox(width: 16),

            // Informações do dispositivo
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    device.name,
                    style: const TextStyle(
                      fontSize: 16,
                      fontWeight: FontWeight.bold,
                    ),
                  ),
                  const SizedBox(height: 4),
                  Text(
                    'MAC: ${device.macAddress}',
                    style: const TextStyle(fontSize: 12, color: Colors.grey),
                  ),
                  if (device.rssi != null) ...[
                    const SizedBox(height: 2),
                    Row(
                      children: [
                        Icon(
                          _getSignalIcon(device.rssi!),
                          size: 16,
                          color: _getSignalColor(device.rssi!),
                        ),
                        const SizedBox(width: 4),
                        Text(
                          '${device.rssi} dBm',
                          style: TextStyle(
                            fontSize: 12,
                            color: _getSignalColor(device.rssi!),
                            fontWeight: FontWeight.w500,
                          ),
                        ),
                      ],
                    ),
                  ],
                  if (isAlreadySaved) ...[
                    const SizedBox(height: 4),
                    Row(
                      children: [
                        Icon(
                          Icons.check_circle,
                          size: 16,
                          color: Colors.green[600],
                        ),
                        const SizedBox(width: 4),
                        Text(
                          'Já adicionado',
                          style: TextStyle(
                            fontSize: 12,
                            color: Colors.green[600],
                            fontWeight: FontWeight.w500,
                          ),
                        ),
                      ],
                    ),
                  ],
                ],
              ),
            ),

            // Botão de ação
            if (!isAlreadySaved)
              IconButton(
                onPressed: () => _addDevice(device),
                icon: const Icon(Icons.add),
                tooltip: 'Adicionar dispositivo',
              )
            else
              Icon(Icons.check, color: Colors.green[600]),
          ],
        ),
      ),
    );
  }

  /// Ícone baseado na força do sinal
  IconData _getSignalIcon(int rssi) {
    if (rssi >= -50) return Icons.wifi;
    if (rssi >= -70) return Icons.wifi;
    if (rssi >= -80) return Icons.wifi_2_bar;
    if (rssi >= -90) return Icons.wifi_1_bar;
    return Icons.wifi_off;
  }

  /// Cor baseada na força do sinal
  Color _getSignalColor(int rssi) {
    if (rssi >= -50) return Colors.green;
    if (rssi >= -70) return Colors.orange;
    return Colors.red;
  }

  /// Exibe snackbar de erro
  void _showErrorSnackBar(String message) {
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(content: Text(message), backgroundColor: Colors.red),
    );
  }

  /// Exibe snackbar de sucesso
  void _showSuccessSnackBar(String message) {
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(content: Text(message), backgroundColor: Colors.green),
    );
  }
}
