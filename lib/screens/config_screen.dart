import 'package:flutter/material.dart';
import 'dart:async';
import '../models/device.dart';
import '../models/wifi_credentials.dart';
import '../services/bluetooth_service.dart';
import '../services/device_storage.dart';

/// Tela para configurar credenciais Wi-Fi em um dispositivo IoT
class ConfigScreen extends StatefulWidget {
  final Device device;

  const ConfigScreen({super.key, required this.device});

  @override
  State<ConfigScreen> createState() => _ConfigScreenState();
}

class _ConfigScreenState extends State<ConfigScreen> {
  final _formKey = GlobalKey<FormState>();
  final _ssidController = TextEditingController();
  final _passwordController = TextEditingController();
  final BluetoothService _bluetoothService = BluetoothService.instance;

  bool _isObscured = true;
  bool _isConnecting = false;
  bool _isSendingCredentials = false;
  String _statusMessage = '';
  StreamSubscription? _statusSubscription;

  @override
  void initState() {
    super.initState();
    _setupStatusStream();
    _loadPreviousCredentials();
  }

  @override
  void dispose() {
    _statusSubscription?.cancel();
    _ssidController.dispose();
    _passwordController.dispose();
    super.dispose();
  }

  /// Configura stream para escutar status do Bluetooth
  void _setupStatusStream() {
    _statusSubscription = _bluetoothService.statusStream.listen((status) {
      if (mounted) {
        setState(() => _statusMessage = status);
      }
    });
  }

  /// Carrega credenciais anteriores se disponíveis
  void _loadPreviousCredentials() {
    if (widget.device.lastConfiguredSSID != null) {
      _ssidController.text = widget.device.lastConfiguredSSID!;
    }
  }

  /// Inicia o processo de configuração
  Future<void> _startConfiguration() async {
    if (!_formKey.currentState!.validate()) {
      return;
    }

    final credentials = WifiCredentials(
      ssid: _ssidController.text.trim(),
      password: _passwordController.text.trim(),
    );

    setState(() {
      _isConnecting = true;
      _statusMessage = 'Conectando ao dispositivo...';
    });

    try {
      // Primeiro, conecta ao dispositivo
      final connected = await _bluetoothService.connectToDevice(
        widget.device.id,
      );

      if (!connected) {
        _showError('Falha ao conectar com o dispositivo');
        return;
      }

      setState(() {
        _isConnecting = false;
        _isSendingCredentials = true;
        _statusMessage = 'Enviando credenciais Wi-Fi...';
      });

      // Atualiza status no storage
      await DeviceStorage.instance.updateDeviceStatus(
        widget.device.id,
        DeviceStatus.configuring,
      );

      // Envia credenciais Wi-Fi
      final success = await _bluetoothService.sendWifiCredentials(
        widget.device.id,
        credentials,
      );

      if (success) {
        _showSuccessDialog();
      } else {
        _showError('Falha ao configurar Wi-Fi no dispositivo');
      }
    } catch (e) {
      _showError('Erro durante configuração: $e');
    } finally {
      setState(() {
        _isConnecting = false;
        _isSendingCredentials = false;
      });
    }
  }

  /// Testa a conexão com o dispositivo
  Future<void> _testConnection() async {
    setState(() {
      _isConnecting = true;
      _statusMessage = 'Testando conexão...';
    });

    try {
      final connected = await _bluetoothService.connectToDevice(
        widget.device.id,
      );

      if (connected) {
        _showSuccessSnackBar('Conexão estabelecida com sucesso');
        // Desconecta após teste
        await _bluetoothService.disconnectFromDevice(widget.device.id);
      } else {
        _showError('Falha ao conectar com o dispositivo');
      }
    } catch (e) {
      _showError('Erro ao testar conexão: $e');
    } finally {
      setState(() {
        _isConnecting = false;
        _statusMessage = '';
      });
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('Configurar Dispositivo'),
        backgroundColor: Theme.of(context).colorScheme.inversePrimary,
        actions: [
          IconButton(
            icon: const Icon(Icons.info),
            onPressed: _showDeviceInfo,
            tooltip: 'Informações do dispositivo',
          ),
        ],
      ),
      body: SingleChildScrollView(
        padding: const EdgeInsets.all(16),
        child: Form(
          key: _formKey,
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              // Card com informações do dispositivo
              _buildDeviceInfoCard(),

              const SizedBox(height: 20),

              // Card de configuração Wi-Fi
              _buildWifiConfigCard(),

              const SizedBox(height: 20),

              // Card de status
              if (_statusMessage.isNotEmpty) _buildStatusCard(),

              const SizedBox(height: 20),

              // Botões de ação
              _buildActionButtons(),
            ],
          ),
        ),
      ),
    );
  }

  /// Card com informações do dispositivo
  Widget _buildDeviceInfoCard() {
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text('Dispositivo', style: Theme.of(context).textTheme.titleLarge),
            const SizedBox(height: 12),
            _buildInfoRow('Nome:', widget.device.name),
            _buildInfoRow('MAC:', widget.device.macAddress),
            _buildInfoRow('Status:', widget.device.statusDescription),
            if (widget.device.lastConfiguredSSID != null)
              _buildInfoRow('Última SSID:', widget.device.lastConfiguredSSID!),
            if (widget.device.lastConfigured != null)
              _buildInfoRow(
                'Configurado em:',
                _formatDateTime(widget.device.lastConfigured!),
              ),
            Row(
              children: [
                Text(
                  'Conectado: ',
                  style: TextStyle(
                    fontWeight: FontWeight.w500,
                    color: Colors.grey[700],
                  ),
                ),
                Icon(
                  widget.device.isConnected ? Icons.check_circle : Icons.cancel,
                  color: widget.device.isConnected ? Colors.green : Colors.red,
                  size: 18,
                ),
                const SizedBox(width: 4),
                Text(
                  widget.device.isConnected ? 'Sim' : 'Não',
                  style: TextStyle(
                    color: widget.device.isConnected
                        ? Colors.green
                        : Colors.red,
                    fontWeight: FontWeight.w500,
                  ),
                ),
              ],
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildInfoRow(String label, String value) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 8),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          SizedBox(
            width: 120,
            child: Text(
              label,
              style: TextStyle(
                fontWeight: FontWeight.w500,
                color: Colors.grey[700],
              ),
            ),
          ),
          Expanded(
            child: Text(
              value,
              style: const TextStyle(fontWeight: FontWeight.w500),
            ),
          ),
        ],
      ),
    );
  }

  /// Card de configuração Wi-Fi
  Widget _buildWifiConfigCard() {
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(
              'Configuração Wi-Fi',
              style: Theme.of(context).textTheme.titleLarge,
            ),
            const SizedBox(height: 16),

            // Campo SSID
            TextFormField(
              controller: _ssidController,
              decoration: const InputDecoration(
                labelText: 'Nome da Rede (SSID)',
                hintText: 'Digite o nome da rede Wi-Fi',
                prefixIcon: Icon(Icons.wifi),
                border: OutlineInputBorder(),
              ),
              validator: (value) {
                if (value == null || value.trim().isEmpty) {
                  return 'Por favor, insira o SSID';
                }
                if (value.trim().length < 2) {
                  return 'SSID deve ter pelo menos 2 caracteres';
                }
                return null;
              },
              textInputAction: TextInputAction.next,
            ),

            const SizedBox(height: 16),

            // Campo Senha
            TextFormField(
              controller: _passwordController,
              obscureText: _isObscured,
              decoration: InputDecoration(
                labelText: 'Senha da Rede',
                hintText: 'Digite a senha da rede Wi-Fi',
                prefixIcon: const Icon(Icons.lock),
                suffixIcon: IconButton(
                  icon: Icon(
                    _isObscured ? Icons.visibility : Icons.visibility_off,
                  ),
                  onPressed: () {
                    setState(() => _isObscured = !_isObscured);
                  },
                ),
                border: const OutlineInputBorder(),
              ),
              validator: (value) {
                if (value == null || value.trim().isEmpty) {
                  return 'Por favor, insira a senha';
                }
                if (value.length < 8) {
                  return 'Senha deve ter pelo menos 8 caracteres';
                }
                return null;
              },
              textInputAction: TextInputAction.done,
              onFieldSubmitted: (_) => _startConfiguration(),
            ),

            const SizedBox(height: 12),

            // Informações adicionais
            Container(
              padding: const EdgeInsets.all(12),
              decoration: BoxDecoration(
                color: Colors.blue[50],
                borderRadius: BorderRadius.circular(8),
                border: Border.all(color: Colors.blue[200]!),
              ),
              child: Row(
                children: [
                  Icon(Icons.info, color: Colors.blue[700], size: 20),
                  const SizedBox(width: 8),
                  Expanded(
                    child: Text(
                      'As credenciais serão enviadas de forma criptografada via BLE',
                      style: TextStyle(fontSize: 12, color: Colors.blue[700]),
                    ),
                  ),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }

  /// Card de status
  Widget _buildStatusCard() {
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Row(
          children: [
            if (_isConnecting || _isSendingCredentials)
              const SizedBox(
                width: 20,
                height: 20,
                child: CircularProgressIndicator(strokeWidth: 2),
              )
            else
              Icon(Icons.info, color: Theme.of(context).primaryColor),
            const SizedBox(width: 12),
            Expanded(
              child: Text(_statusMessage, style: const TextStyle(fontSize: 14)),
            ),
          ],
        ),
      ),
    );
  }

  /// Botões de ação
  Widget _buildActionButtons() {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        // Botão principal de configuração
        ElevatedButton.icon(
          onPressed: (_isConnecting || _isSendingCredentials)
              ? null
              : _startConfiguration,
          icon: _isSendingCredentials
              ? const SizedBox(
                  width: 16,
                  height: 16,
                  child: CircularProgressIndicator(
                    strokeWidth: 2,
                    valueColor: AlwaysStoppedAnimation<Color>(Colors.white),
                  ),
                )
              : const Icon(Icons.send),
          label: Text(
            _isSendingCredentials
                ? 'Enviando Credenciais...'
                : 'Configurar Wi-Fi',
          ),
          style: ElevatedButton.styleFrom(
            padding: const EdgeInsets.symmetric(vertical: 16),
          ),
        ),

        const SizedBox(height: 12),

        // Botão de teste de conexão
        OutlinedButton.icon(
          onPressed: (_isConnecting || _isSendingCredentials)
              ? null
              : _testConnection,
          icon: _isConnecting
              ? const SizedBox(
                  width: 16,
                  height: 16,
                  child: CircularProgressIndicator(strokeWidth: 2),
                )
              : const Icon(Icons.bluetooth_connected),
          label: Text(
            _isConnecting ? 'Testando Conexão...' : 'Testar Conexão BLE',
          ),
          style: OutlinedButton.styleFrom(
            padding: const EdgeInsets.symmetric(vertical: 16),
          ),
        ),
      ],
    );
  }

  /// Exibe informações detalhadas do dispositivo
  void _showDeviceInfo() {
    showDialog(
      context: context,
      builder: (context) => AlertDialog(
        title: Text(widget.device.name),
        content: SingleChildScrollView(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            mainAxisSize: MainAxisSize.min,
            children: [
              _buildInfoRow('ID:', widget.device.id),
              _buildInfoRow('MAC Address:', widget.device.macAddress),
              _buildInfoRow('Service UUID:', widget.device.serviceUuid),
              _buildInfoRow('Status:', widget.device.statusDescription),
              if (widget.device.rssi != null)
                _buildInfoRow('RSSI:', '${widget.device.rssi} dBm'),
              if (widget.device.lastConfiguredSSID != null)
                _buildInfoRow(
                  'Última SSID:',
                  widget.device.lastConfiguredSSID!,
                ),
              if (widget.device.lastConfigured != null)
                _buildInfoRow(
                  'Configurado em:',
                  _formatDateTime(widget.device.lastConfigured!),
                ),
            ],
          ),
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

  /// Exibe dialog de sucesso
  void _showSuccessDialog() {
    showDialog(
      context: context,
      barrierDismissible: false,
      builder: (context) => AlertDialog(
        title: Row(
          children: [
            Icon(Icons.check_circle, color: Colors.green[600]),
            const SizedBox(width: 8),
            const Text('Sucesso!'),
          ],
        ),
        content: const Text(
          'Credenciais Wi-Fi enviadas com sucesso!\n\n'
          'O dispositivo tentará se conectar à rede Wi-Fi especificada. '
          'O modo BLE será desabilitado automaticamente após a conexão.',
        ),
        actions: [
          TextButton(
            onPressed: () {
              Navigator.pop(context); // Fecha dialog
              Navigator.pop(context, true); // Volta para tela anterior
            },
            child: const Text('OK'),
          ),
        ],
      ),
    );
  }

  String _formatDateTime(DateTime dateTime) {
    return '${dateTime.day}/${dateTime.month}/${dateTime.year} '
        '${dateTime.hour.toString().padLeft(2, '0')}:'
        '${dateTime.minute.toString().padLeft(2, '0')}';
  }

  /// Exibe snackbar de erro
  void _showError(String message) {
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(
        content: Text(message),
        backgroundColor: Colors.red,
        duration: const Duration(seconds: 4),
      ),
    );
  }

  /// Exibe snackbar de sucesso
  void _showSuccessSnackBar(String message) {
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(
        content: Text(message),
        backgroundColor: Colors.green,
        duration: const Duration(seconds: 2),
      ),
    );
  }
}
