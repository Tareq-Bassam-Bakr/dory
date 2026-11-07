import 'dart:async';

import 'package:dory_app/app/localization/l10n.dart';
import 'package:dory_app/app/router/app_routes.dart';
import 'package:dory_app/app/theme/theme.dart';
import 'package:dory_app/core/config/map_config.dart';
import 'package:dory_app/core/location/location_service.dart';
import 'package:dory_app/core/widgets/widgets.dart';
import 'package:dory_app/features/businesses/domain/entities/business_category.dart';
import 'package:dory_app/features/businesses/presentation/providers/business_providers.dart';
import 'package:dory_app/features/discovery/domain/entities/discovery_entities.dart';
import 'package:dory_app/features/discovery/presentation/providers/discovery_providers.dart';
import 'package:dory_app/features/discovery/presentation/widgets/discovery_labels.dart';
import 'package:dory_app/features/discovery/presentation/widgets/discovery_widgets.dart';
import 'package:dory_app/features/maps/presentation/providers/location_providers.dart';
import 'package:dory_app/features/maps/presentation/providers/map_providers.dart';
import 'package:dory_app/features/maps/presentation/widgets/location_picker.dart';
import 'package:dory_app/shared/components/category_icon.dart';
import 'package:dory_app/shared/components/failure_message.dart';
import 'package:flutter_map/flutter_map.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:latlong2/latlong.dart';
import 'package:material_ui/material_ui.dart';
import 'package:url_launcher/url_launcher.dart';

/// Branches on an OpenStreetMap map (docs/MAPS_AND_LOCATION.md).
///
/// The tile source comes from [mapTileConfigProvider], so this page knows
/// nothing about any provider. Its attribution is always visible.
class DiscoveryMapPage extends ConsumerStatefulWidget {
  const DiscoveryMapPage({super.key});

  @override
  ConsumerState<DiscoveryMapPage> createState() => _DiscoveryMapPageState();
}

class _DiscoveryMapPageState extends ConsumerState<DiscoveryMapPage> {
  final MapController _map = MapController();
  PublicBranch? _selected;
  String? _category;

  @override
  void dispose() {
    _map.dispose();
    super.dispose();
  }

  static LatLng _latLng(GeoPoint point) =>
      LatLng(point.latitude, point.longitude);

  /// A zoom that roughly fits the search radius on a phone.
  static double _zoomFor(double radiusKm) => switch (radiusKm) {
    <= 5 => 13,
    <= 10 => 12,
    <= 25 => 11,
    <= 50 => 10,
    _ => 9,
  };

  void _recenter(CustomerLocation location) {
    final GeoPoint? point = location.point;
    if (point != null) {
      _map.move(_latLng(point), _zoomFor(location.radiusKm));
    }
  }

  @override
  Widget build(BuildContext context) {
    final AppLocalizations l10n = context.l10n;
    final MapTileConfig? tiles = ref.watch(mapTileConfigProvider);
    final CustomerLocation location = ref.watch(customerLocationProvider);
    final GeoPoint? center = location.point;

    final List<Widget> actions = <Widget>[
      IconButton(
        tooltip: l10n.mapShowList,
        icon: const Icon(Icons.view_list_rounded),
        onPressed: () => context.canPop()
            ? context.pop()
            : context.go(AppRoutes.customerExplore),
      ),
    ];

    if (tiles == null) {
      return DoryPageScaffold(
        title: l10n.mapTitle,
        scrollable: false,
        actions: actions,
        body: DoryEmptyState(
          icon: Icons.map_outlined,
          title: l10n.mapUnavailableTitle,
          message: l10n.mapUnavailableMessage,
        ),
      );
    }

    if (center == null) {
      return DoryPageScaffold(
        title: l10n.mapTitle,
        scrollable: false,
        actions: actions,
        body: DoryEmptyState(
          icon: Icons.location_on_outlined,
          title: l10n.homeNoLocationTitle,
          message: l10n.homeNoLocationMessage,
          action: DoryStatusAction(
            label: l10n.locationChoose,
            onPressed: () => showLocationPicker(context),
          ),
        ),
      );
    }

    final AsyncValue<List<PublicBranch>> branches = ref.watch(
      mapBranchesProvider(_category),
    );
    final List<PublicBranch> located = <PublicBranch>[
      for (final PublicBranch branch
          in branches.value ?? const <PublicBranch>[])
        if (branch.location != null) branch,
    ];
    final PublicBranch? selected = _selected;

    return DoryPageScaffold(
      title: l10n.mapTitle,
      scrollable: false,
      padding: EdgeInsets.zero,
      maxContentWidth: double.infinity,
      actions: actions,
      body: Stack(
        children: <Widget>[
          FlutterMap(
            mapController: _map,
            options: MapOptions(
              initialCenter: _latLng(center),
              initialZoom: _zoomFor(location.radiusKm),
              minZoom: 3,
              maxZoom: 18,
              onTap: (_, _) => setState(() => _selected = null),
            ),
            children: <Widget>[
              TileLayer(
                urlTemplate: tiles.urlTemplate,
                userAgentPackageName: tiles.userAgentPackage,
                maxNativeZoom: tiles.maxNativeZoom,
                additionalOptions: <String, String>{
                  if (tiles.apiKey != null) 'key': tiles.apiKey!,
                },
                tileProvider: ref.watch(mapTileProviderProvider),
              ),
              MarkerLayer(
                markers: <Marker>[
                  if (location.isDevice)
                    Marker(
                      point: _latLng(center),
                      width: 24,
                      height: 24,
                      child: Semantics(
                        container: true,
                        label: l10n.mapYourLocation,
                        child: const DecoratedBox(
                          decoration: BoxDecoration(
                            color: AppColors.secondary,
                            shape: BoxShape.circle,
                            border: Border.fromBorderSide(
                              BorderSide(color: AppColors.surface, width: 3),
                            ),
                          ),
                        ),
                      ),
                    ),
                  for (final PublicBranch branch in located)
                    Marker(
                      point: _latLng(branch.location!),
                      width: 44,
                      height: 44,
                      child: _BranchMarker(
                        branch: branch,
                        selected: selected?.id == branch.id,
                        onTap: () => setState(() => _selected = branch),
                      ),
                    ),
                ],
              ),
              _Attribution(tiles: tiles),
            ],
          ),
          Positioned(
            top: AppSpacing.sm,
            left: 0,
            right: 0,
            child: _CategoryFilter(
              selected: _category,
              onChanged: (category) => setState(() {
                _category = category;
                _selected = null;
              }),
            ),
          ),
          if (branches.isLoading)
            const Positioned(
              top: 64,
              left: 0,
              right: 0,
              child: Center(
                child: SizedBox.square(
                  dimension: 24,
                  child: CircularProgressIndicator(strokeWidth: 2),
                ),
              ),
            ),
          if (branches.hasError && !branches.isLoading)
            Positioned(
              top: 64,
              left: AppSpacing.pageGutter,
              right: AppSpacing.pageGutter,
              child: DoryNotice(
                message: failureMessage(l10n, branches.error!),
                tone: DoryNoticeTone.error,
              ),
            ),
          if (branches.hasValue && located.isEmpty && !branches.isLoading)
            Positioned(
              top: 64,
              left: AppSpacing.pageGutter,
              right: AppSpacing.pageGutter,
              child: DoryNotice(message: l10n.mapNoBranches),
            ),
          // Above the attribution, which must stay visible.
          PositionedDirectional(
            end: AppSpacing.md,
            bottom: selected == null ? 40 : 176,
            child: FloatingActionButton.small(
              heroTag: 'recenter',
              tooltip: l10n.mapRecenter,
              onPressed: () => _recenter(location),
              child: const Icon(Icons.my_location_rounded),
            ),
          ),
          if (selected != null)
            Positioned(
              left: AppSpacing.pageGutter,
              right: AppSpacing.pageGutter,
              bottom: 32,
              child: BranchCard(branch: selected),
            ),
        ],
      ),
    );
  }
}

class _BranchMarker extends StatelessWidget {
  const _BranchMarker({
    required this.branch,
    required this.selected,
    required this.onTap,
  });

  final PublicBranch branch;
  final bool selected;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final AppLocalizations l10n = context.l10n;
    final bool accepting = branch.acceptingQueues > 0;
    final int? wait = branch.lowestWaitMinutes;

    return Semantics(
      button: true,
      label: <String>[
        branch.business.name,
        branch.name,
        if (accepting && wait != null) waitLabel(l10n, wait),
      ].join(', '),
      excludeSemantics: true,
      child: GestureDetector(
        onTap: onTap,
        child: DecoratedBox(
          decoration: BoxDecoration(
            color: selected
                ? AppColors.primary
                : (accepting ? AppColors.success : AppColors.surface),
            shape: BoxShape.circle,
            border: Border.all(color: AppColors.primary, width: 2),
          ),
          child: Icon(
            categoryIcon(branch.business.category?.icon ?? ''),
            size: 22,
            color: selected || accepting
                ? AppColors.surface
                : AppColors.primary,
          ),
        ),
      ),
    );
  }
}

class _CategoryFilter extends ConsumerWidget {
  const _CategoryFilter({required this.selected, required this.onChanged});

  final String? selected;
  final ValueChanged<String?> onChanged;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final List<BusinessCategory> categories =
        ref.watch(businessCategoriesProvider).value ??
        const <BusinessCategory>[];
    final String language = Localizations.localeOf(context).languageCode;

    return SizedBox(
      height: 48,
      child: ListView(
        scrollDirection: Axis.horizontal,
        padding: const EdgeInsets.symmetric(horizontal: AppSpacing.pageGutter),
        children: <Widget>[
          for (final BusinessCategory category in categories)
            Padding(
              padding: const EdgeInsetsDirectional.only(end: AppSpacing.sm),
              child: FilterChip(
                label: Text(category.nameFor(language)),
                selected: selected == category.slug,
                onSelected: (value) => onChanged(value ? category.slug : null),
              ),
            ),
        ],
      ),
    );
  }
}

/// flutter_map's attribution widget, always visible and clickable.
///
/// SimpleAttributionWidget and not RichAttributionWidget: the rich one
/// hides behind a button and needs a Material ancestor from
/// package:flutter/material.dart, which this app does not use. The
/// background color is passed for the same reason.
class _Attribution extends StatelessWidget {
  const _Attribution({required this.tiles});

  final MapTileConfig tiles;

  @override
  Widget build(BuildContext context) {
    final Uri? link = Uri.tryParse(tiles.attributionUrl);
    return Directionality(
      textDirection: TextDirection.ltr,
      child: DefaultTextStyle.merge(
        style: Theme.of(context).textTheme.labelSmall,
        // Scales down rather than overflowing on narrow screens, so a long
        // credit from another tile provider is never cut off.
        child: Align(
          alignment: Alignment.bottomRight,
          child: FittedBox(
            fit: BoxFit.scaleDown,
            alignment: Alignment.bottomRight,
            child: SimpleAttributionWidget(
              backgroundColor: AppColors.surface.withValues(alpha: 0.9),
              source: Text(
                tiles.attribution,
                style: const TextStyle(
                  color: AppColors.primary,
                  decoration: TextDecoration.underline,
                ),
              ),
              onTap: link == null ? null : () => unawaited(launchUrl(link)),
            ),
          ),
        ),
      ),
    );
  }
}
