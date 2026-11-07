import 'package:dory_app/core/config/map_config.dart';
import 'package:flutter_map/flutter_map.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

/// The tile source of this build, or null when none is configured.
final Provider<MapTileConfig?> mapTileConfigProvider = Provider<MapTileConfig?>(
  (ref) => MapConfig.tiles,
  name: 'mapTileConfigProvider',
);

/// How tiles are fetched. Null uses flutter_map's network provider, which
/// caches tiles as public tile servers ask. Tests inject an offline one.
final Provider<TileProvider?> mapTileProviderProvider = Provider<TileProvider?>(
  (ref) => null,
  name: 'mapTileProviderProvider',
);
