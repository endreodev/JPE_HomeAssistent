import 'package:flutter/material.dart';
import '../models/device.dart';
import '../services/device_storage.dart';
import 'scan_screen.dart';
import 'config_screen.dart';

/// Tela principal que exibe a lista de dispositivos cadastrados
class HomeScreen extends StatefulWidget {
  const HomeScreen({super.key});

  @override
  State<HomeScreen> createState() => _HomeScreenState();
}

class _HomeScreenState extends State<HomeScreen> {
  List<Device> _devices = [];
  bool _isLoading = true;
  Map<String, int> _statistics = {};

  @override
  void initState() {
    super.initState();
    _loadDevices();
    _loadStatistics();
  }

  /// Carrega a lista de dispositivos salvos
  Future<void> _loadDevices() async {
    setState(() => _isLoading = true);

    try {
      final devices = await DeviceStorage.instance.loadDevices();
      if (mounted) {
        setState(() {
          _devices = devices;
          _isLoading = false;
        });
      }
    } catch (e) {
      if (mounted) {
        setState(() => _isLoading = false);
        _showErrorSnackBar('Erro ao carregar dispositivos: $e');
      }
    }
  }

  /// Carrega estatísticas dos dispositivos
  Future<void> _loadStatistics() async {
    try {
      final stats = await DeviceStorage.instance.getDeviceStatistics();
      if (mounted) {
        setState(() => _statistics = stats);
      }
    } catch (e) {
      print('Erro ao carregar estatísticas: $e');
    }
  }

  /// Navega para a tela de escaneamento
  void _navigateToScanScreen() async {
    final result = await Navigator.push(
      context,
      MaterialPageRoute(builder: (context) => const ScanScreen()),
    );

    // Recarrega a lista se um dispositivo foi adicionado
    if (result == true) {
      _loadDevices();
      _loadStatistics();
    }
  }

  /// Navega para a tela de configuração de um dispositivo
  void _navigateToConfigScreen(Device device) async {
    final result = await Navigator.push(
      context,
      MaterialPageRoute(builder: (context) => ConfigScreen(device: device)),
    );

    // Recarrega a lista se houve mudanças
    if (result == true) {
      _loadDevices();
      _loadStatistics();
    }
  }

  /// Remove um dispositivo da lista
  void _removeDevice(Device device) async {
    final confirmed = await _showConfirmationDialog(
      'Remover Dispositivo',
      'Tem certeza que deseja remover o dispositivo "${device.name}"?',
    );

    if (confirmed) {
      try {
        await DeviceStorage.instance.removeDevice(device.id);
        await _loadDevices();
        await _loadStatistics();
        _showSuccessSnackBar('Dispositivo removido com sucesso');
      } catch (e) {
        _showErrorSnackBar('Erro ao remover dispositivo: $e');
      }
    }
  }

  /// Exibe detalhes do dispositivo
  void _showDeviceDetails(Device device) {
    showDialog(
      context: context,
      builder: (context) => AlertDialog(
        title: Text(device.name),
        content: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          mainAxisSize: MainAxisSize.min,
          children: [
            _buildDetailRow('ID:', device.id),
            _buildDetailRow('MAC:', device.macAddress),
            _buildDetailRow('Status:', device.statusDescription),
            if (device.lastConfiguredSSID != null)
              _buildDetailRow('Última SSID:', device.lastConfiguredSSID!),
            if (device.lastConfigured != null)
              _buildDetailRow(
                'Configurado em:',
                _formatDateTime(device.lastConfigured!),
              ),
            _buildDetailRow('Conectado:', device.isConnected ? 'Sim' : 'Não'),
            if (device.rssi != null)
              _buildDetailRow('RSSI:', '${device.rssi} dBm'),
          ],
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context),
            child: const Text('Fechar'),
          ),
        ],
      ),
    );
  }

  Widget _buildDetailRow(String label, String value) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 2),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          SizedBox(
            width: 100,
            child: Text(
              label,
              style: const TextStyle(fontWeight: FontWeight.bold),
            ),
          ),
          Expanded(child: Text(value)),
        ],
      ),
    );
  }

  String _formatDateTime(DateTime dateTime) {
    return '${dateTime.day}/${dateTime.month}/${dateTime.year} '
        '${dateTime.hour.toString().padLeft(2, '0')}:'
        '${dateTime.minute.toString().padLeft(2, '0')}';
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('IoT Home Assistant'),
        backgroundColor: Theme.of(context).colorScheme.inversePrimary,
        actions: [
          IconButton(
            icon: const Icon(Icons.refresh),
            onPressed: () {
              _loadDevices();
              _loadStatistics();
            },
            tooltip: 'Atualizar lista',
          ),
        ],
      ),
      body: _isLoading
          ? const Center(child: CircularProgressIndicator())
          : Column(
              children: [
                // Card de estatísticas
                if (_statistics.isNotEmpty) _buildStatisticsCard(),

                // Lista de dispositivos
                Expanded(
                  child: _devices.isEmpty
                      ? _buildEmptyState()
                      : _buildDevicesList(),
                ),
              ],
            ),
      floatingActionButton: FloatingActionButton(
        onPressed: _navigateToScanScreen,
        tooltip: 'Escanear dispositivos',
        child: const Icon(Icons.search),
      ),
    );
  }

  /// Card com estatísticas dos dispositivos
  Widget _buildStatisticsCard() {
    return Card(
      margin: const EdgeInsets.all(16),
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Row(
          mainAxisAlignment: MainAxisAlignment.spaceAround,
          children: [
            _buildStatItem(
              'Total',
              _statistics['total'].toString(),
              Icons.devices,
              Colors.blue,
            ),
            _buildStatItem(
              'Configurados',
              _statistics['configured'].toString(),
              Icons.check_circle,
              Colors.green,
            ),
            _buildStatItem(
              'Pendentes',
              _statistics['notConfigured'].toString(),
              Icons.warning,
              Colors.orange,
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildStatItem(
    String label,
    String value,
    IconData icon,
    Color color,
  ) {
    return Column(
      mainAxisSize: MainAxisSize.min,
      children: [
        Icon(icon, color: color, size: 24),
        const SizedBox(height: 4),
        Text(
          value,
          style: TextStyle(
            fontSize: 18,
            fontWeight: FontWeight.bold,
            color: color,
          ),
        ),
        Text(label, style: const TextStyle(fontSize: 12)),
      ],
    );
  }

  /// Estado vazio quando não há dispositivos
  Widget _buildEmptyState() {
    return Center(
      child: Column(
        mainAxisAlignment: MainAxisAlignment.center,
        children: [
          Icon(Icons.devices_other_outlined, size: 80, color: Colors.grey[400]),
          const SizedBox(height: 16),
          Text(
            'Nenhum dispositivo cadastrado',
            style: TextStyle(
              fontSize: 18,
              color: Colors.grey[600],
              fontWeight: FontWeight.w500,
            ),
          ),
          const SizedBox(height: 8),
          Text(
            'Toque no botão + para escanear dispositivos',
            style: TextStyle(fontSize: 14, color: Colors.grey[500]),
          ),
          const SizedBox(height: 24),
          ElevatedButton.icon(
            onPressed: _navigateToScanScreen,
            icon: const Icon(Icons.search),
            label: const Text('Escanear Dispositivos'),
          ),
        ],
      ),
    );
  }

  /// Lista de dispositivos
  Widget _buildDevicesList() {
    return ListView.builder(
      padding: const EdgeInsets.all(16),
      itemCount: _devices.length,
      itemBuilder: (context, index) {
        final device = _devices[index];
        return _buildDeviceCard(device);
      },
    );
  }

  /// Card individual de dispositivo
  Widget _buildDeviceCard(Device device) {
    return Card(
      margin: const EdgeInsets.only(bottom: 12),
      child: InkWell(
        onTap: () => _showDeviceDetails(device),
        borderRadius: BorderRadius.circular(12),
        child: Padding(
          padding: const EdgeInsets.all(16),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Row(
                children: [
                  // Ícone do status
                  Container(
                    padding: const EdgeInsets.all(8),
                    decoration: BoxDecoration(
                      color: _getStatusColor(device.status).withOpacity(0.1),
                      borderRadius: BorderRadius.circular(8),
                    ),
                    child: Icon(
                      _getStatusIcon(device.status),
                      color: _getStatusColor(device.status),
                      size: 24,
                    ),
                  ),
                  const SizedBox(width: 12),

                  // Nome e informações
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
                          device.statusDescription,
                          style: TextStyle(
                            fontSize: 14,
                            color: _getStatusColor(device.status),
                            fontWeight: FontWeight.w500,
                          ),
                        ),
                        if (device.lastConfiguredSSID != null) ...[
                          const SizedBox(height: 2),
                          Text(
                            'SSID: ${device.lastConfiguredSSID}',
                            style: const TextStyle(
                              fontSize: 12,
                              color: Colors.grey,
                            ),
                          ),
                        ],
                      ],
                    ),
                  ),

                  // Botões de ação
                  PopupMenuButton<String>(
                    onSelected: (value) {
                      switch (value) {
                        case 'configure':
                          _navigateToConfigScreen(device);
                          break;
                        case 'details':
                          _showDeviceDetails(device);
                          break;
                        case 'remove':
                          _removeDevice(device);
                          break;
                      }
                    },
                    itemBuilder: (context) => [
                      const PopupMenuItem(
                        value: 'configure',
                        child: ListTile(
                          leading: Icon(Icons.settings),
                          title: Text('Configurar'),
                          contentPadding: EdgeInsets.zero,
                        ),
                      ),
                      const PopupMenuItem(
                        value: 'details',
                        child: ListTile(
                          leading: Icon(Icons.info),
                          title: Text('Detalhes'),
                          contentPadding: EdgeInsets.zero,
                        ),
                      ),
                      const PopupMenuItem(
                        value: 'remove',
                        child: ListTile(
                          leading: Icon(Icons.delete, color: Colors.red),
                          title: Text('Remover'),
                          contentPadding: EdgeInsets.zero,
                        ),
                      ),
                    ],
                  ),
                ],
              ),
            ],
          ),
        ),
      ),
    );
  }

  Color _getStatusColor(DeviceStatus status) {
    switch (status) {
      case DeviceStatus.configured:
        return Colors.green;
      case DeviceStatus.notConfigured:
        return Colors.orange;
      case DeviceStatus.configuring:
        return Colors.blue;
      case DeviceStatus.error:
        return Colors.red;
    }
  }

  IconData _getStatusIcon(DeviceStatus status) {
    switch (status) {
      case DeviceStatus.configured:
        return Icons.check_circle;
      case DeviceStatus.notConfigured:
        return Icons.warning;
      case DeviceStatus.configuring:
        return Icons.refresh;
      case DeviceStatus.error:
        return Icons.error;
    }
  }

  /// Exibe dialog de confirmação
  Future<bool> _showConfirmationDialog(String title, String message) async {
    final result = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: Text(title),
        content: Text(message),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context, false),
            child: const Text('Cancelar'),
          ),
          TextButton(
            onPressed: () => Navigator.pop(context, true),
            child: const Text('Confirmar'),
          ),
        ],
      ),
    );
    return result ?? false;
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
