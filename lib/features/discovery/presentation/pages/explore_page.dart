import 'package:dory_app/app/localization/l10n.dart';
import 'package:dory_app/app/router/app_routes.dart';
import 'package:dory_app/app/theme/theme.dart';
import 'package:dory_app/core/widgets/widgets.dart';
import 'package:dory_app/features/businesses/domain/entities/business_category.dart';
import 'package:dory_app/features/businesses/presentation/providers/business_providers.dart';
import 'package:dory_app/features/discovery/domain/entities/discovery_entities.dart';
import 'package:dory_app/features/discovery/presentation/providers/discovery_providers.dart';
import 'package:dory_app/features/discovery/presentation/widgets/discovery_widgets.dart';
import 'package:dory_app/features/maps/presentation/providers/location_providers.dart';
import 'package:dory_app/features/maps/presentation/widgets/location_picker.dart';
import 'package:dory_app/shared/components/failure_message.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:material_ui/material_ui.dart';

/// Search and browse. Results come from the server a page at a time; typing
/// is debounced and every new search cancels the one in flight.
class ExplorePage extends ConsumerStatefulWidget {
  const ExplorePage({super.key});

  @override
  ConsumerState<ExplorePage> createState() => _ExplorePageState();
}

class _ExplorePageState extends ConsumerState<ExplorePage> {
  late final TextEditingController _search = TextEditingController(
    text: ref.read(exploreProvider).filters.text,
  );

  @override
  void dispose() {
    _search.dispose();
    super.dispose();
  }

  ExploreController get _explore => ref.read(exploreProvider.notifier);

  void _useRecent(String term) {
    _search.text = term;
    _explore.setFilters(ref.read(exploreProvider).filters.copyWith(text: term));
  }

  void _clear() {
    _search.clear();
    _explore.setFilters(ref.read(exploreProvider).filters.copyWith(text: ''));
  }

  /// "Nearby" needs a location: without one, the customer sets one first.
  Future<void> _toggleNearby(ExploreFilters filters) async {
    if (!filters.nearby && !ref.read(customerLocationProvider).isKnown) {
      await showLocationPicker(context);
      if (!mounted || !ref.read(customerLocationProvider).isKnown) {
        return;
      }
    }
    _explore.setFilters(filters.copyWith(nearby: !filters.nearby));
  }

  @override
  Widget build(BuildContext context) {
    final AppLocalizations l10n = context.l10n;
    final ExploreState state = ref.watch(exploreProvider);
    final ExploreFilters filters = state.filters;
    final List<String> recent = ref.watch(recentSearchesProvider);
    final List<BusinessCategory> categories =
        ref.watch(businessCategoriesProvider).value ??
        const <BusinessCategory>[];
    final String language = Localizations.localeOf(context).languageCode;

    return DoryPageScaffold(
      title: l10n.exploreTitle,
      scrollable: false,
      padding: EdgeInsets.zero,
      actions: <Widget>[
        IconButton(
          tooltip: l10n.exploreOpenMap,
          icon: const Icon(Icons.map_outlined),
          onPressed: () => context.push(AppRoutes.customerMap),
        ),
      ],
      body: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: <Widget>[
          Padding(
            padding: const EdgeInsets.symmetric(
              horizontal: AppSpacing.pageGutter,
            ),
            child: TextField(
              controller: _search,
              textInputAction: TextInputAction.search,
              onChanged: (value) {
                _explore.setText(value);
                setState(() {});
              },
              onSubmitted: (value) =>
                  ref.read(recentSearchesProvider.notifier).add(value),
              decoration: InputDecoration(
                hintText: l10n.homeSearchHint,
                prefixIcon: const Icon(Icons.search_rounded),
                suffixIcon: _search.text.isEmpty
                    ? null
                    : IconButton(
                        tooltip: l10n.exploreClearSearch,
                        icon: const Icon(Icons.close_rounded),
                        onPressed: _clear,
                      ),
              ),
            ),
          ),
          if (_search.text.isEmpty && recent.isNotEmpty)
            _RecentSearches(searches: recent, onSelected: _useRecent),
          const SizedBox(height: AppSpacing.sm),
          SizedBox(
            height: 48,
            child: ListView(
              scrollDirection: Axis.horizontal,
              padding: const EdgeInsets.symmetric(
                horizontal: AppSpacing.pageGutter,
              ),
              children: <Widget>[
                _FilterGap(
                  child: ActionChip(
                    avatar: const Icon(Icons.tune_rounded, size: 18),
                    label: Text(
                      filters.activeCount == 0
                          ? l10n.exploreFilters
                          : l10n.exploreFiltersCount(filters.activeCount),
                    ),
                    onPressed: () => _showFilters(context),
                  ),
                ),
                _FilterGap(
                  child: FilterChip(
                    label: Text(l10n.exploreFilterNearby),
                    selected: filters.nearby,
                    onSelected: (_) => _toggleNearby(filters),
                  ),
                ),
                _FilterGap(
                  child: FilterChip(
                    label: Text(l10n.exploreFilterAccepting),
                    selected: filters.accepting,
                    onSelected: (selected) => _explore.setFilters(
                      filters.copyWith(accepting: selected),
                    ),
                  ),
                ),
                for (final BusinessCategory category in categories)
                  _FilterGap(
                    child: FilterChip(
                      label: Text(category.nameFor(language)),
                      selected: filters.categorySlug == category.slug,
                      onSelected: (selected) => _explore.setFilters(
                        filters.copyWith(
                          categorySlug: selected ? category.slug : null,
                        ),
                      ),
                    ),
                  ),
              ],
            ),
          ),
          Expanded(child: _Results(state: state)),
        ],
      ),
    );
  }

  Future<void> _showFilters(BuildContext context) {
    return showModalBottomSheet<void>(
      context: context,
      isScrollControlled: true,
      useSafeArea: true,
      builder: (context) => const _FilterSheet(),
    );
  }
}

class _FilterGap extends StatelessWidget {
  const _FilterGap({required this.child});

  final Widget child;

  @override
  Widget build(BuildContext context) => Padding(
    padding: const EdgeInsetsDirectional.only(end: AppSpacing.sm),
    child: child,
  );
}

class _RecentSearches extends ConsumerWidget {
  const _RecentSearches({required this.searches, required this.onSelected});

  final List<String> searches;
  final ValueChanged<String> onSelected;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final AppLocalizations l10n = context.l10n;
    return Padding(
      padding: const EdgeInsets.fromLTRB(
        AppSpacing.pageGutter,
        AppSpacing.sm,
        AppSpacing.pageGutter,
        0,
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: <Widget>[
          Row(
            children: <Widget>[
              Expanded(
                child: Text(
                  l10n.exploreRecentSearches,
                  style: Theme.of(context).textTheme.labelMedium,
                ),
              ),
              TextButton(
                onPressed: () =>
                    ref.read(recentSearchesProvider.notifier).clear(),
                child: Text(l10n.exploreClearRecent),
              ),
            ],
          ),
          Wrap(
            spacing: AppSpacing.sm,
            children: <Widget>[
              for (final String search in searches)
                ActionChip(
                  label: Text(search),
                  onPressed: () => onSelected(search),
                ),
            ],
          ),
        ],
      ),
    );
  }
}

class _Results extends ConsumerWidget {
  const _Results({required this.state});

  final ExploreState state;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final AppLocalizations l10n = context.l10n;
    final ExploreController explore = ref.read(exploreProvider.notifier);
    final Object? error = state.error;

    if (state.items.isEmpty) {
      if (state.isLoading) {
        return const DoryLoadingIndicator();
      }
      if (error != null) {
        return DoryErrorState(
          icon: Icons.cloud_off_rounded,
          title: l10n.commonGenericErrorTitle,
          message: failureMessage(l10n, error),
          retry: DoryStatusAction(
            label: l10n.commonRetry,
            onPressed: explore.retry,
          ),
        );
      }
      return DoryEmptyState(
        icon: Icons.search_off_rounded,
        title: l10n.exploreEmptyTitle,
        message: l10n.exploreEmptyMessage,
      );
    }

    return NotificationListener<ScrollNotification>(
      onNotification: (notification) {
        if (notification.metrics.extentAfter < 400) {
          explore.loadMore();
        }
        return false;
      },
      child: ListView.separated(
        padding: const EdgeInsets.fromLTRB(
          AppSpacing.pageGutter,
          AppSpacing.xs,
          AppSpacing.pageGutter,
          AppSpacing.xl,
        ),
        itemCount: state.items.length + 1,
        separatorBuilder: (context, index) =>
            const SizedBox(height: AppSpacing.sm),
        itemBuilder: (context, index) {
          if (index < state.items.length) {
            return BusinessCard(business: state.items[index]);
          }
          if (state.isLoadingMore) {
            return const SizedBox(height: 64, child: DoryLoadingIndicator());
          }
          return error == null
              ? const SizedBox.shrink()
              : DoryButton(
                  label: l10n.commonRetry,
                  variant: DoryButtonVariant.tertiary,
                  onPressed: explore.loadMore,
                );
        },
      ),
    );
  }
}

/// Filters beyond the quick chips. Each choice applies immediately.
class _FilterSheet extends ConsumerWidget {
  const _FilterSheet();

  static const List<int> _waits = <int>[15, 30, 60];

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final AppLocalizations l10n = context.l10n;
    final TextTheme text = Theme.of(context).textTheme;
    final ExploreFilters filters = ref.watch(
      exploreProvider.select((state) => state.filters),
    );
    final bool located = ref.watch(
      customerLocationProvider.select((location) => location.isKnown),
    );
    final ExploreController explore = ref.read(exploreProvider.notifier);

    String sortLabel(DiscoverySort sort) => switch (sort) {
      DiscoverySort.relevance => l10n.exploreSortRelevance,
      DiscoverySort.nearest => l10n.exploreSortNearest,
      DiscoverySort.wait => l10n.exploreSortWait,
      DiscoverySort.rating => l10n.exploreSortRating,
    };

    return Padding(
      padding: const EdgeInsets.all(AppSpacing.pageGutter),
      child: ListView(
        shrinkWrap: true,
        children: <Widget>[
          Text(l10n.exploreFilters, style: text.titleLarge),
          const SizedBox(height: AppSpacing.md),
          SwitchListTile(
            contentPadding: EdgeInsets.zero,
            title: Text(l10n.exploreFilterOpen),
            value: filters.openQueue,
            onChanged: (value) =>
                explore.setFilters(filters.copyWith(openQueue: value)),
          ),
          SwitchListTile(
            contentPadding: EdgeInsets.zero,
            title: Text(l10n.exploreFilterAccepting),
            value: filters.accepting,
            onChanged: (value) =>
                explore.setFilters(filters.copyWith(accepting: value)),
          ),
          const SizedBox(height: AppSpacing.sm),
          Text(l10n.exploreMaxWait, style: text.titleSmall),
          Wrap(
            spacing: AppSpacing.sm,
            children: <Widget>[
              for (final int minutes in _waits)
                ChoiceChip(
                  label: Text(l10n.discoveryWaitMinutes(minutes)),
                  selected: filters.maxWaitMinutes == minutes,
                  onSelected: (selected) => explore.setFilters(
                    filters.copyWith(maxWaitMinutes: selected ? minutes : null),
                  ),
                ),
            ],
          ),
          const SizedBox(height: AppSpacing.md),
          Text(l10n.exploreMinRating, style: text.titleSmall),
          Wrap(
            spacing: AppSpacing.sm,
            children: <Widget>[
              for (final double rating in const <double>[4, 4.5])
                ChoiceChip(
                  label: Text(l10n.exploreRatingAtLeast(rating.toString())),
                  selected: filters.minRating == rating,
                  onSelected: (selected) => explore.setFilters(
                    filters.copyWith(minRating: selected ? rating : null),
                  ),
                ),
            ],
          ),
          const SizedBox(height: AppSpacing.md),
          Text(l10n.exploreSort, style: text.titleSmall),
          Wrap(
            spacing: AppSpacing.sm,
            children: <Widget>[
              for (final DiscoverySort sort in DiscoverySort.values)
                ChoiceChip(
                  label: Text(sortLabel(sort)),
                  selected: filters.sort == sort,
                  onSelected:
                      sort == DiscoverySort.nearest &&
                          !(located && filters.nearby)
                      ? null
                      : (_) => explore.setFilters(filters.copyWith(sort: sort)),
                ),
            ],
          ),
          const SizedBox(height: AppSpacing.md),
          DoryButton(
            label: l10n.exploreResetFilters,
            variant: DoryButtonVariant.tertiary,
            onPressed: () =>
                explore.setFilters(ExploreFilters(text: filters.text)),
          ),
        ],
      ),
    );
  }
}
