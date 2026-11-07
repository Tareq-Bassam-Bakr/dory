import 'dart:async';

import 'package:dory_app/app/localization/l10n.dart';
import 'package:dory_app/app/router/app_routes.dart';
import 'package:dory_app/app/theme/theme.dart';
import 'package:dory_app/core/widgets/widgets.dart';
import 'package:dory_app/features/businesses/presentation/providers/business_providers.dart';
import 'package:dory_app/features/discovery/domain/entities/discovery_entities.dart';
import 'package:dory_app/features/discovery/presentation/widgets/discovery_widgets.dart';
import 'package:dory_app/features/favorites/presentation/providers/favorite_providers.dart';
import 'package:dory_app/shared/components/failure_message.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:material_ui/material_ui.dart';

/// Saved businesses: on the server for signed-in customers, on this device
/// for visitors. Visitors are told which one they have.
class FavoritesPage extends ConsumerWidget {
  const FavoritesPage({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final AppLocalizations l10n = context.l10n;
    final bool signedIn = ref.watch(currentUserIdProvider) != null;

    return DoryPageScaffold(
      title: l10n.favoritesTitle,
      scrollable: false,
      padding: EdgeInsets.zero,
      body: signedIn ? const _ServerFavorites() : const _LocalFavorites(),
    );
  }
}

class _Empty extends StatelessWidget {
  const _Empty();

  @override
  Widget build(BuildContext context) {
    final AppLocalizations l10n = context.l10n;
    return DoryEmptyState(
      icon: Icons.favorite_outline_rounded,
      title: l10n.favoritesEmptyTitle,
      message: l10n.favoritesEmptyMessage,
      action: DoryStatusAction(
        label: l10n.ticketsFindQueue,
        onPressed: () => context.go(AppRoutes.customerExplore),
      ),
    );
  }
}

class _ServerFavorites extends ConsumerWidget {
  const _ServerFavorites();

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final AppLocalizations l10n = context.l10n;
    final FavoriteListState state = ref.watch(serverFavoritesProvider);
    final ServerFavorites favorites = ref.read(
      serverFavoritesProvider.notifier,
    );
    final Object? error = state.error;

    if (state.items.isEmpty) {
      if (state.isLoading) {
        return const DoryLoadingIndicator();
      }
      return error != null
          ? DoryErrorState(
              title: l10n.commonGenericErrorTitle,
              message: failureMessage(l10n, error),
              retry: DoryStatusAction(
                label: l10n.commonRetry,
                onPressed: favorites.reload,
              ),
            )
          : const _Empty();
    }

    return RefreshIndicator(
      onRefresh: favorites.reload,
      child: NotificationListener<ScrollNotification>(
        onNotification: (notification) {
          if (notification.metrics.extentAfter < 300) {
            unawaited(favorites.loadMore());
          }
          return false;
        },
        child: ListView.separated(
          padding: const EdgeInsets.all(AppSpacing.pageGutter),
          itemCount: state.items.length,
          separatorBuilder: (context, index) =>
              const SizedBox(height: AppSpacing.sm),
          itemBuilder: (context, index) {
            final PublicBusiness business = state.items[index];
            return BusinessCard(business: business);
          },
        ),
      ),
    );
  }
}

class _LocalFavorites extends ConsumerWidget {
  const _LocalFavorites();

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final AppLocalizations l10n = context.l10n;
    final List<LocalFavorite> favorites = ref.watch(localFavoritesProvider);

    return ListView(
      padding: const EdgeInsets.all(AppSpacing.pageGutter),
      children: <Widget>[
        DoryNotice(message: l10n.favoritesLocalNotice),
        const SizedBox(height: AppSpacing.sm),
        DoryButton(
          label: l10n.welcomeLogin,
          variant: DoryButtonVariant.tertiary,
          onPressed: () => context.go(AppRoutes.login),
        ),
        const SizedBox(height: AppSpacing.md),
        if (favorites.isEmpty)
          const _Empty()
        else
          for (final LocalFavorite favorite in favorites)
            Padding(
              padding: const EdgeInsets.only(bottom: AppSpacing.sm),
              child: DoryCard(
                padding: EdgeInsets.zero,
                child: ListTile(
                  title: Text(favorite.name),
                  subtitle: favorite.city == null ? null : Text(favorite.city!),
                  trailing: IconButton(
                    tooltip: l10n.favoriteRemove,
                    icon: const Icon(
                      Icons.favorite_rounded,
                      color: AppColors.error,
                    ),
                    onPressed: () => ref
                        .read(localFavoritesProvider.notifier)
                        .toggle(favorite),
                  ),
                  onTap: () =>
                      context.push(AppRoutes.customerBusiness(favorite.slug)),
                ),
              ),
            ),
      ],
    );
  }
}
