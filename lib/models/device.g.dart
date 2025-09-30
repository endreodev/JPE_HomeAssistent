// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'device.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Device _$DeviceFromJson(Map<String, dynamic> json) => Device(
  id: json['id'] as String,
  name: json['name'] as String,
  macAddress: json['macAddress'] as String,
  serviceUuid:
      json['serviceUuid'] as String? ?? '12345678-1234-1234-1234-1234567890ab',
  lastConfiguredSSID: json['lastConfiguredSSID'] as String?,
  status:
      $enumDecodeNullable(_$DeviceStatusEnumMap, json['status']) ??
      DeviceStatus.notConfigured,
  lastConfigured: json['lastConfigured'] == null
      ? null
      : DateTime.parse(json['lastConfigured'] as String),
  rssi: (json['rssi'] as num?)?.toInt(),
  isConnected: json['isConnected'] as bool? ?? false,
);

Map<String, dynamic> _$DeviceToJson(Device instance) => <String, dynamic>{
  'id': instance.id,
  'name': instance.name,
  'macAddress': instance.macAddress,
  'serviceUuid': instance.serviceUuid,
  'lastConfiguredSSID': instance.lastConfiguredSSID,
  'status': _$DeviceStatusEnumMap[instance.status]!,
  'lastConfigured': instance.lastConfigured?.toIso8601String(),
  'rssi': instance.rssi,
  'isConnected': instance.isConnected,
};

const _$DeviceStatusEnumMap = {
  DeviceStatus.notConfigured: 'notConfigured',
  DeviceStatus.configured: 'configured',
  DeviceStatus.configuring: 'configuring',
  DeviceStatus.error: 'error',
};
