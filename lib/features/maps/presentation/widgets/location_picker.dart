import 'package:dory_app/app/localization/l10n.dart';
import 'package:dory_app/app/theme/theme.dart';
import 'package:dory_app/core/location/location_service.dart';
import 'package:dory_app/core/widgets/widgets.dart';
import 'package:dory_app/features/discovery/domain/entities/discovery_entities.dart';
import 'package:dory_app/features/discovery/presentation/providers/discovery_providers.dart';
import 'package:dory_app/features/maps/presentation/providers/location_providers.dart';
import 'package:dory_app/shared/components/failure_message.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:material_ui/material_ui.dart';

/// The chip showing the current location. Tapping it opens the picker.
class LocationChip extends ConsumerWidget {
  const LocationChip({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final AppLocalizations l10n = context.l10n;
    final CustomerLocation location = ref.watch(customerLocationProvider);
    final String label = location.isDevice
        ? l10n.locationNearMe
        : location.city ?? l10n.locationChoose;

    return ActionChip(
      avatar: Icon(
        location.isKnown
            ? Icons.location_on_rounded
            : Icons.location_off_outlined,
        size: 18,
        color: AppColors.primary,
      ),
      label: Text(label, maxLines: 1, overflow: TextOverflow.ellipsis),
      onPressed: () => showLocationPicker(context),
    );
  }
}

/// Lets the customer use their location or pick a city by hand.
Future<void> showLocationPicker(BuildContext context) {
  return showModalBottomSheet<void>(
    context: context,
    isScrollControlled: true,
    useRootNavigator: true,
    useSafeArea: true,
    builder: (context) => const _LocationSheet(),
  );
}

class _LocationSheet extends ConsumerStatefulWidget {
  const _LocationSheet();

  @override
  ConsumerState<_LocationSheet> createState() => _LocationSheetState();
}

class _LocationSheetState extends ConsumerState<_LocationSheet> {
  String _search = '';

  Future<void> _useDevice() async {
    final LocationAccess access = await ref
        .read(customerLocationProvider.notifier)
        .useDevice();
    if (mounted &&
        access == LocationAccess.granted &&
        ref.read(customerLocationProvider).isDevice) {
      Navigator.of(context).pop();
    }
  }

  Future<void> _choose(DiscoveryCity city) async {
    await ref.read(customerLocationProvider.notifier).chooseCity(city);
    if (mounted) {
      Navigator.of(context).pop();
    }
  }

  @override
  Widget build(BuildContext context) {
    final AppLocalizations l10n = context.l10n;
    final TextTheme text = Theme.of(context).textTheme;
    final CustomerLocation location = ref.watch(customerLocationProvider);
    final AsyncValue<List<DiscoveryCity>> cities = ref.watch(
      citiesProvider(_search),
    );

    return Padding(
      padding: EdgeInsets.fromLTRB(
        AppSpacing.pageGutter,
        AppSpacing.lg,
        AppSpacing.pageGutter,
        AppSpacing.lg + MediaQuery.viewInsetsOf(context).bottom,
      ),
      child: ConstrainedBox(
        constraints: BoxConstraints(
          maxHeight: MediaQuery.sizeOf(context).height * 0.8,
        ),
        child: ListView(
          shrinkWrap: true,
          children: <Widget>[
            Text(l10n.locationSheetTitle, style: text.titleLarge),
            const SizedBox(height: AppSpacing.xs),
            // Explains why, before any system permission prompt appears.
            Text(l10n.locationWhy, style: text.bodyMedium),
            const SizedBox(height: AppSpacing.md),
            DoryButton(
              label: l10n.locationUseDevice,
              icon: Icons.my_location_rounded,
              isLoading: location.isLocating,
              onPressed: _useDevice,
            ),
            ?_accessNotice(l10n, location),
            const SizedBox(height: AppSpacing.lg),
            Text(l10n.locationRadiusLabel, style: text.titleSmall),
            const SizedBox(height: AppSpacing.xs),
            Wrap(
              spacing: AppSpacing.sm,
              children: <Widget>[
                for (final double radius in CustomerLocation.radiusChoices)
                  ChoiceChip(
                    label: Text(
                      l10n.discoveryDistanceKm(radius.toStringAsFixed(0)),
                    ),
                    selected: location.radiusKm == radius,
                    onSelected: (_) => ref
                        .read(customerLocationProvider.notifier)
                        .setRadius(radius),
                  ),
              ],
            ),
            const SizedBox(height: AppSpacing.lg),
            Text(l10n.locationChooseCity, style: text.titleSmall),
            const SizedBox(height: AppSpacing.sm),
            DoryTextField(
              label: l10n.locationSearchCity,
              prefixIcon: Icons.search_rounded,
              onChanged: (value) => setState(() => _search = value.trim()),
            ),
            const SizedBox(height: AppSpacing.sm),
            switch (cities) {
              AsyncData(:final List<DiscoveryCity> value) when value.isEmpty =>
                Padding(
                  padding: const EdgeInsets.all(AppSpacing.md),
                  child: Text(l10n.locationNoCities, style: text.bodyMedium),
                ),
              AsyncData(:final List<DiscoveryCity> value) => Column(
                children: <Widget>[
                  for (final DiscoveryCity city in value)
                    ListTile(
                      contentPadding: EdgeInsets.zero,
                      leading: const Icon(Icons.location_city_outlined),
                      title: Text(city.city),
                      subtitle: Text(
                        l10n.discoveryBranchCount(city.branchesCount),
                      ),
                      enabled: city.location != null,
                      onTap: () => _choose(city),
                    ),
                ],
              ),
              AsyncError(:final Object error) => DoryNotice(
                message: failureMessage(l10n, error),
                tone: DoryNoticeTone.error,
              ),
              _ => const SizedBox(height: 64, child: DoryLoadingIndicator()),
            },
            if (location.isKnown)
              DoryButton(
                label: l10n.locationClear,
                variant: DoryButtonVariant.tertiary,
                onPressed: () {
                  ref.read(customerLocationProvider.notifier).clear();
                  Navigator.of(context).pop();
                },
              ),
          ],
        ),
      ),
    );
  }

  /// Explains a refusal. Browsing keeps working either way.
  Widget? _accessNotice(AppLocalizations l10n, CustomerLocation location) {
    final LocationService service = ref.read(locationServiceProvider);
    final (
      String message,
      String? action,
      VoidCallback? onAction,
    ) = switch (location.access) {
      LocationAccess.denied => (l10n.locationDenied, null, null),
      LocationAccess.permanentlyDenied => (
        l10n.locationPermanentlyDenied,
        l10n.locationOpenSettings,
        service.openAppSettings,
      ),
      LocationAccess.serviceDisabled => (
        l10n.locationServiceDisabled,
        l10n.locationOpenSettings,
        service.openLocationSettings,
      ),
      LocationAccess.granted when !location.isDevice => (
        l10n.locationUnavailable,
        null,
        null,
      ),
      _ => ('', null, null),
    };
    if (message.isEmpty) {
      return null;
    }
    return Padding(
      padding: const EdgeInsets.only(top: AppSpacing.sm),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: <Widget>[
          DoryNotice(message: message, tone: DoryNoticeTone.warning),
          if (action != null)
            DoryButton(
              label: action,
              variant: DoryButtonVariant.tertiary,
              onPressed: onAction,
            ),
        ],
      ),
    );
  }
}
