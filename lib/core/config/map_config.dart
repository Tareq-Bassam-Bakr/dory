import 'package:flutter/foundation.dart';

/// Where map tiles come from and how they must be credited. Nothing outside
/// this file and the map widget knows the provider, so changing it never
/// touches discovery logic (docs/OSM_TILE_PROVIDER.md).
@immutable
class MapTileConfig {
  const MapTileConfig({
    required this.urlTemplate,
    required this.attribution,
    required this.attributionUrl,
    required this.userAgentPackage,
    this.apiKey,
    this.maxNativeZoom = 19,
  });

  /// `{z}/{x}/{y}` tile URL. May contain `{key}`, replaced by [apiKey].
  final String urlTemplate;

  /// Shown on the map at all times.
  final String attribution;

  /// Opened when the attribution is tapped.
  final String attributionUrl;

  /// Identifies this app in the tile request's User-Agent.
  final String userAgentPackage;

  /// Supplied at build time, never written in the repository.
  final String? apiKey;
  final int maxNativeZoom;
}

/// Build-time map settings.
///
/// Debug and profile builds fall back to OpenStreetMap's public tile server,
/// which is meant for light development use only: its usage policy does not
/// allow heavy or commercial traffic. Release builds have no such fallback.
/// Without `--dart-define=DORY_MAP_TILE_URL=...` they show the list instead
/// of a map, rather than sending production traffic to volunteers' servers.
abstract final class MapConfig {
  static const String _definedUrl = String.fromEnvironment('DORY_MAP_TILE_URL');
  static const String _definedKey = String.fromEnvironment('DORY_MAP_TILE_KEY');
  static const String _definedAttribution = String.fromEnvironment(
    'DORY_MAP_ATTRIBUTION',
  );
  static const String _definedAttributionUrl = String.fromEnvironment(
    'DORY_MAP_ATTRIBUTION_URL',
  );

  static const String developmentTileUrl =
      'https://tile.openstreetmap.org/{z}/{x}/{y}.png';
  static const String osmAttribution = 'OpenStreetMap contributors';
  static const String osmCopyrightUrl =
      'https://www.openstreetmap.org/copyright';

  /// The same identifier as the Android application id.
  static const String userAgentPackage = 'com.dory.app';

  /// The tile source of this build, or null when none is configured.
  static MapTileConfig? get tiles {
    final String url = _definedUrl.isNotEmpty
        ? _definedUrl
        : (kReleaseMode ? '' : developmentTileUrl);
    if (url.isEmpty) {
      return null;
    }
    return MapTileConfig(
      urlTemplate: url,
      attribution: _definedAttribution.isNotEmpty
          ? _definedAttribution
          : osmAttribution,
      attributionUrl: _definedAttributionUrl.isNotEmpty
          ? _definedAttributionUrl
          : osmCopyrightUrl,
      userAgentPackage: userAgentPackage,
      apiKey: _definedKey.isEmpty ? null : _definedKey,
    );
  }
}
