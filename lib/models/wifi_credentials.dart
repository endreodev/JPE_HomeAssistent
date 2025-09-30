/// Modelo para dados de configuração Wi-Fi
class WifiCredentials {
  /// Nome da rede Wi-Fi (SSID)
  final String ssid;

  /// Senha da rede Wi-Fi
  final String password;

  /// Tipo de segurança da rede (WPA2, WEP, etc.)
  final String? securityType;

  const WifiCredentials({
    required this.ssid,
    required this.password,
    this.securityType,
  });

  /// Converte as credenciais para o formato esperado pelo dispositivo
  /// Formato: "SSID:PASSWORD" ou "SSID:PASSWORD:SECURITY"
  String toDeviceFormat() {
    if (securityType != null) {
      return '$ssid:$password:$securityType';
    }
    return '$ssid:$password';
  }

  /// Cria credenciais a partir do formato do dispositivo
  factory WifiCredentials.fromDeviceFormat(String data) {
    final parts = data.split(':');
    if (parts.length >= 2) {
      return WifiCredentials(
        ssid: parts[0],
        password: parts[1],
        securityType: parts.length > 2 ? parts[2] : null,
      );
    }
    throw FormatException('Formato inválido para credenciais Wi-Fi: $data');
  }

  Map<String, dynamic> toJson() => {
    'ssid': ssid,
    'password': password,
    'securityType': securityType,
  };

  factory WifiCredentials.fromJson(Map<String, dynamic> json) =>
      WifiCredentials(
        ssid: json['ssid'] as String,
        password: json['password'] as String,
        securityType: json['securityType'] as String?,
      );

  @override
  String toString() =>
      'WifiCredentials{ssid: $ssid, securityType: $securityType}';
}
