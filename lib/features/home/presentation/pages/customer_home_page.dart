import 'package:dory_app/app/localization/l10n.dart';
import 'package:dory_app/app/router/app_routes.dart';
import 'package:dory_app/app/theme/theme.dart';
import 'package:dory_app/core/widgets/widgets.dart';
import 'package:dory_app/features/authentication/domain/entities/auth_user.dart';
import 'package:dory_app/features/authentication/presentation/providers/auth_controller.dart';
import 'package:dory_app/features/authentication/presentation/providers/auth_state.dart';
import 'package:dory_app/features/discovery/domain/entities/discovery_entities.dart';
import 'package:dory_app/features/discovery/presentation/providers/discovery_providers.dart';
import 'package:dory_app/features/discovery/presentation/widgets/discovery_widgets.dart';
import 'package:dory_app/features/home/presentation/widgets/category_strip.dart';
import 'package:dory_app/features/home/presentation/widgets/home_search_entry.dart';
import 'package:dory_app/features/maps/presentation/providers/location_providers.dart';
import 'package:dory_app/features/maps/presentation/widgets/location_picker.dart';
import 'package:dory_app/features/notifications/presentation/widgets/notification_widgets.dart';
import 'package:dory_app/shared/enums/app_workspace.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:material_ui/material_ui.dart';

/// Customer home: greeting and location, search, categories, and live
/// sections built from real records. Sections fail independently, and none
/// of them needs a location or an account.
class CustomerHomePage extends ConsumerWidget {
  const CustomerHomePage({super.key});

  Future<void> _refresh(WidgetRef ref) async {
    ref
      ..invalidate(featuredBusinessesProvider)
      ..invalidate(nearbyBranchesProvider)
      ..invalidate(availableQueuesProvider);
    await ref.read(featuredBusinessesProvider.future);
  }

  void _openExplore(BuildContext context, WidgetRef ref, String? category) {
    final ExploreController explore = ref.read(exploreProvider.notifier);
    explore.setFilters(
      ref.read(exploreProvider).filters.copyWith(categorySlug: category),
    );
    context.go(AppRoutes.customerExplore);
  }

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final AppLocalizations l10n = context.l10n;
    final AuthState auth = ref.watch(authControllerProvider);
    final AuthUser? user = auth is Authenticated ? auth.user : null;

    return DoryPageScaffold(
      titleWidget: const DoryWordmark(fontSize: 22),
      scrollable: false,
      padding: EdgeInsets.zero,
      actions: <Widget>[
        if (user?.hasActiveMemberships ?? false)
          IconButton(
            tooltip: l10n.homeOpenBusinessWorkspace,
            icon: const Icon(Icons.storefront_outlined),
            onPressed: () =>
                context.go(AppRoutes.workspaceHome(AppWorkspace.business)),
          ),
        const NotificationBell(),
      ],
      body: RefreshIndicator(
        onRefresh: () => _refresh(ref),
        child: ListView(
          padding: const EdgeInsets.fromLTRB(
            AppSpacing.pageGutter,
            AppSpacing.sm,
            AppSpacing.pageGutter,
            AppSpacing.xl,
          ),
          children: <Widget>[
            Text(
              user == null
                  ? l10n.homeTitle
                  : l10n.homeGreeting(user.name.split(' ').first),
              style: Theme.of(context).textTheme.headlineSmall,
            ),
            const SizedBox(height: AppSpacing.sm),
            const Align(
              alignment: AlignmentDirectional.centerStart,
              child: LocationChip(),
            ),
            const SizedBox(height: AppSpacing.md),
            HomeSearchEntry(
              hint: l10n.homeSearchHint,
              onTap: () => context.go(AppRoutes.customerExplore),
            ),
            const SizedBox(height: AppSpacing.xl),
            DorySectionHeader(title: l10n.homeCategoriesTitle),
            CategoryStrip(
              onSelected: (category) =>
                  _openExplore(context, ref, category.slug),
            ),
            const SizedBox(height: AppSpacing.xl),
            DorySectionHeader(title: l10n.homeAvailableQueuesTitle),
            const _AvailableQueuesSection(),
            const SizedBox(height: AppSpacing.xl),
            DorySectionHeader(title: l10n.homeNearbyTitle),
            const _NearbySection(),
            const SizedBox(height: AppSpacing.xl),
            DorySectionHeader(
              title: l10n.homeFeaturedTitle,
              trailing: TextButton(
                onPressed: () => context.go(AppRoutes.customerExplore),
                child: Text(l10n.homeSeeAll),
              ),
            ),
            const _FeaturedSection(),
          ],
        ),
      ),
    );
  }
}

class _FeaturedSection extends ConsumerWidget {
  const _FeaturedSection();

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final AppLocalizations l10n = context.l10n;
    return SectionValue<List<PublicBusiness>>(
      value: ref.watch(featuredBusinessesProvider),
      onRetry: () => ref.invalidate(featuredBusinessesProvider),
      data: (businesses) => businesses.isEmpty
          ? DoryCard(
              child: DoryEmptyState(
                icon: Icons.storefront_outlined,
                title: l10n.homeFeaturedEmptyTitle,
                message: l10n.homeFeaturedEmptyMessage,
              ),
            )
          : CardColumn(
              children: <Widget>[
                for (final PublicBusiness business in businesses)
                  BusinessCard(business: business),
              ],
            ),
    );
  }
}

class _NearbySection extends ConsumerWidget {
  const _NearbySection();

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final AppLocalizations l10n = context.l10n;
    final bool located = ref.watch(
      customerLocationProvider.select((location) => location.isKnown),
    );

    if (!located) {
      return DoryCard(
        child: DoryEmptyState(
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

    return SectionValue<List<PublicBranch>>(
      value: ref.watch(nearbyBranchesProvider),
      onRetry: () => ref.invalidate(nearbyBranchesProvider),
      data: (branches) => branches.isEmpty
          ? DoryCard(
              child: DoryEmptyState(
                icon: Icons.location_on_outlined,
                title: l10n.homeNearbyEmptyTitle,
                message: l10n.homeNearbyEmptyMessage,
              ),
            )
          : CardColumn(
              children: <Widget>[
                for (final PublicBranch branch in branches)
                  BranchCard(branch: branch),
              ],
            ),
    );
  }
}

class _AvailableQueuesSection extends ConsumerWidget {
  const _AvailableQueuesSection();

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final AppLocalizations l10n = context.l10n;
    return SectionValue<List<AvailableQueue>>(
      value: ref.watch(availableQueuesProvider),
      onRetry: () => ref.invalidate(availableQueuesProvider),
      data: (queues) => queues.isEmpty
          ? DoryCard(
              child: DoryEmptyState(
                icon: Icons.hourglass_empty_rounded,
                title: l10n.homeNoQueuesTitle,
                message: l10n.homeNoQueuesMessage,
              ),
            )
          : CardColumn(
              children: <Widget>[
                for (final AvailableQueue queue in queues)
                  AvailableQueueCard(item: queue),
              ],
            ),
    );
  }
}
