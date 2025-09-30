import 'dart:convert';
import 'dart:math';
import 'dart:typed_data';
import 'package:crypto/crypto.dart';
import 'package:encrypt/encrypt.dart';

/// Serviço para criptografia e descriptografia de credenciais Wi-Fi
class EncryptionService {
  static const int _ivLength = 16; // 128 bits para IV

  static EncryptionService? _instance;
  late final Encrypter _encrypter;
  late final Key _key;

  EncryptionService._() {
    _initializeEncryption();
  }

  /// Singleton instance
  static EncryptionService get instance {
    _instance ??= EncryptionService._();
    return _instance!;
  }

  /// Inicializa a criptografia com uma chave derivada
  void _initializeEncryption() {
    // Em um app real, você deve usar uma chave mais segura
    // Por exemplo, derivada do device ID ou armazenada de forma segura
    const String baseKey = 'IoTHomeAssistant2024SecureKey!@#';
    final keyBytes = _deriveKey(baseKey);
    _key = Key(Uint8List.fromList(keyBytes));
    _encrypter = Encrypter(AES(_key));
  }

  /// Deriva uma chave de 256 bits a partir de uma string base
  List<int> _deriveKey(String baseKey) {
    final bytes = utf8.encode(baseKey);
    final hash = sha256.convert(bytes);
    return hash.bytes;
  }

  /// Gera um IV (Initialization Vector) aleatório
  IV _generateRandomIV() {
    final random = Random.secure();
    final ivBytes = List<int>.generate(_ivLength, (i) => random.nextInt(256));
    return IV(Uint8List.fromList(ivBytes));
  }

  /// Criptografa as credenciais Wi-Fi
  /// Retorna uma string base64 que contém IV + dados criptografados
  String encryptWifiCredentials(String ssid, String password) {
    try {
      final credentials = '$ssid:$password';
      final iv = _generateRandomIV();
      final encrypted = _encrypter.encrypt(credentials, iv: iv);

      // Combina IV + dados criptografados
      final combined = Uint8List.fromList([...iv.bytes, ...encrypted.bytes]);

      return base64.encode(combined);
    } catch (e) {
      print('Erro ao criptografar credenciais: $e');
      rethrow;
    }
  }

  /// Descriptografa as credenciais Wi-Fi
  /// Retorna um Map com 'ssid' e 'password'
  Map<String, String> decryptWifiCredentials(String encryptedData) {
    try {
      final combined = base64.decode(encryptedData);

      // Separa IV dos dados criptografados
      final iv = IV(Uint8List.fromList(combined.take(_ivLength).toList()));
      final encryptedBytes = combined.skip(_ivLength).toList();
      final encrypted = Encrypted(Uint8List.fromList(encryptedBytes));

      final decrypted = _encrypter.decrypt(encrypted, iv: iv);
      final parts = decrypted.split(':');

      if (parts.length >= 2) {
        return {
          'ssid': parts[0],
          'password': parts
              .sublist(1)
              .join(':'), // Reagrupa caso a senha contenha ':'
        };
      } else {
        throw FormatException(
          'Formato inválido de credenciais descriptografadas',
        );
      }
    } catch (e) {
      print('Erro ao descriptografar credenciais: $e');
      rethrow;
    }
  }

  /// Criptografa dados genéricos (para uso futuro)
  String encryptData(String data) {
    try {
      final iv = _generateRandomIV();
      final encrypted = _encrypter.encrypt(data, iv: iv);

      final combined = Uint8List.fromList([...iv.bytes, ...encrypted.bytes]);

      return base64.encode(combined);
    } catch (e) {
      print('Erro ao criptografar dados: $e');
      rethrow;
    }
  }

  /// Descriptografa dados genéricos (para uso futuro)
  String decryptData(String encryptedData) {
    try {
      final combined = base64.decode(encryptedData);

      final iv = IV(Uint8List.fromList(combined.take(_ivLength).toList()));
      final encryptedBytes = combined.skip(_ivLength).toList();
      final encrypted = Encrypted(Uint8List.fromList(encryptedBytes));

      return _encrypter.decrypt(encrypted, iv: iv);
    } catch (e) {
      print('Erro ao descriptografar dados: $e');
      rethrow;
    }
  }

  /// Gera um hash SHA-256 para verificação de integridade
  String generateHash(String data) {
    final bytes = utf8.encode(data);
    final digest = sha256.convert(bytes);
    return digest.toString();
  }

  /// Verifica se um hash corresponde aos dados
  bool verifyHash(String data, String expectedHash) {
    final actualHash = generateHash(data);
    return actualHash == expectedHash;
  }

  /// Gera uma chave de sessão temporária para comunicação BLE
  String generateSessionKey() {
    final random = Random.secure();
    final keyBytes = List<int>.generate(16, (i) => random.nextInt(256));
    return base64.encode(keyBytes);
  }

  /// Criptografa dados com uma chave de sessão específica
  String encryptWithSessionKey(String data, String sessionKey) {
    try {
      final keyBytes = base64.decode(sessionKey);
      final key = Key(Uint8List.fromList(keyBytes));
      final encrypter = Encrypter(AES(key));

      final iv = _generateRandomIV();
      final encrypted = encrypter.encrypt(data, iv: iv);

      final combined = Uint8List.fromList([...iv.bytes, ...encrypted.bytes]);

      return base64.encode(combined);
    } catch (e) {
      print('Erro ao criptografar com chave de sessão: $e');
      rethrow;
    }
  }
}
