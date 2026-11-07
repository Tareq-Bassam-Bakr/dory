import 'package:dory_app/app/localization/l10n.dart';
import 'package:dory_app/app/theme/theme.dart';
import 'package:dory_app/core/errors/app_failure.dart';
import 'package:dory_app/features/businesses/presentation/providers/business_providers.dart';
import 'package:dory_app/features/discovery/domain/entities/discovery_entities.dart';
import 'package:dory_app/features/favorites/presentation/providers/favorite_providers.dart';
import 'package:dory_app/shared/components/confirm_dialog.dart';
import 'package:dory_app/shared/components/failure_message.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:material_ui/material_ui.dart';

/// Saves a business. Signed-in customers save on the server; signed-out
/// visitors save on this device only, which the favorites screen explains.
class FavoriteButton extends ConsumerStatefulWidget {
  const FavoriteButton({super.key, required this.business});

  final PublicBusiness business;

  @override
  ConsumerState<FavoriteButton> createState() => _FavoriteButtonState();
}

class _FavoriteButtonState extends ConsumerState<FavoriteButton> {
  bool _busy = false;

  Future<void> _toggle(bool saved) async {
    final PublicBusiness business = widget.business;
    if (ref.read(currentUserIdProvider) == null) {
      await ref
          .read(localFavoritesProvider.notifier)
          .toggle(
            LocalFavorite(
              id: business.id,
              slug: business.slug,
              name: business.name,
              city: business.city,
            ),
          );
      return;
    }

    setState(() => _busy = true);
    try {
      await ref
          .read(serverFavoritesProvider.notifier)
          .setFavorite(business, !saved);
    } on AppFailure catch (failure) {
      if (mounted) {
        showResultMessage(context, failureMessage(context.l10n, failure));
      }
    } finally {
      if (mounted) {
        setState(() => _busy = false);
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    final AppLocalizations l10n = context.l10n;
    final String id = widget.business.id;
    final bool signedIn = ref.watch(currentUserIdProvider) != null;
    final bool saved = signedIn
        ? ref.watch(favoriteChangesProvider)[id] ?? widget.business.isFavorite
        : ref.watch(localFavoritesProvider).any((item) => item.id == id);

    return IconButton(
      tooltip: saved ? l10n.favoriteRemove : l10n.favoriteAdd,
      onPressed: _busy ? null : () => _toggle(saved),
      icon: Icon(
        saved ? Icons.favorite_rounded : Icons.favorite_outline_rounded,
        color: saved ? AppColors.error : AppColors.textMuted,
      ),
    );
  }
}
