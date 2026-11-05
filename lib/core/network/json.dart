/// A decoded JSON object.
typedef JsonMap = Map<String, Object?>;

/// Converts a decoded JSON value to a [JsonMap], or throws a
/// [FormatException] naming [context].
JsonMap asJsonMap(Object? value, {String context = 'value'}) {
  if (value is Map<String, Object?>) {
    return value;
  }
  if (value is Map) {
    return value.map((key, item) => MapEntry(key.toString(), item));
  }
  throw FormatException('Expected a JSON object for $context.');
}

/// Typed, null-safe readers for API payloads. A missing or mistyped
/// required field throws a [FormatException], which the API layer reports
/// as an invalid response instead of crashing later.
extension JsonMapReader on JsonMap {
  String string(String key) {
    final Object? value = this[key];
    if (value is String) {
      return value;
    }
    throw FormatException('Expected a string at "$key".');
  }

  String? stringOrNull(String key) {
    final Object? value = this[key];
    return value is String ? value : null;
  }

  int integer(String key) {
    final Object? value = this[key];
    if (value is num) {
      return value.toInt();
    }
    throw FormatException('Expected a number at "$key".');
  }

  int? integerOrNull(String key) {
    final Object? value = this[key];
    return value is num ? value.toInt() : null;
  }

  double? doubleOrNull(String key) {
    final Object? value = this[key];
    return value is num ? value.toDouble() : null;
  }

  bool boolean(String key, {bool fallback = false}) {
    final Object? value = this[key];
    return value is bool ? value : fallback;
  }

  DateTime? dateTimeOrNull(String key) {
    final String? value = stringOrNull(key);
    return value == null ? null : DateTime.tryParse(value);
  }

  JsonMap object(String key) => asJsonMap(this[key], context: '"$key"');

  JsonMap? objectOrNull(String key) {
    final Object? value = this[key];
    return value == null ? null : asJsonMap(value, context: '"$key"');
  }

  List<JsonMap> objectList(String key) {
    final Object? value = this[key];
    if (value == null) {
      return const <JsonMap>[];
    }
    if (value is List) {
      return <JsonMap>[
        for (final Object? item in value) asJsonMap(item, context: '"$key"'),
      ];
    }
    throw FormatException('Expected a list at "$key".');
  }
}
