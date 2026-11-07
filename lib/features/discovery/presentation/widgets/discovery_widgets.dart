import 'package:dory_app/app/localization/l10n.dart';
import 'package:dory_app/app/router/app_routes.dart';
import 'package:dory_app/app/theme/theme.dart';
import 'package:dory_app/core/widgets/widgets.dart';
import 'package:dory_app/features/businesses/domain/entities/business_category.dart';
import 'package:dory_app/features/discovery/domain/entities/discovery_entities.dart';
import 'package:dory_app/features/discovery/presentation/widgets/discovery_labels.dart';
import 'package:dory_app/features/favorites/presentation/widgets/favorite_button.dart';
import 'package:dory_app/shared/components/category_icon.dart';
import 'package:dory_app/shared/components/failure_message.dart';
import 'package:dory_app/shared/components/status_chip.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:material_ui/material_ui.dart';

/// A business's logo, or its category icon when it has none. Images load
/// lazily (lists build rows on demand) and are decoded at display size.
class BusinessLogo extends StatelessWidget {
  const BusinessLogo({super.key, this.url, this.category, this.size = 52});

  final String? url;
  final BusinessCategory? category;
  final double size;

  @override
  Widget build(BuildContext context) {
    final Widget fallback = ColoredBox(
      color: AppColors.primaryContainer,
      child: Center(
        child: Icon(
          categoryIcon(category?.icon ?? ''),
          color: AppColors.primary,
          size: size * 0.5,
        ),
      ),
    );
    final String? image = url;
    final double ratio = MediaQuery.devicePixelRatioOf(context);

    return ClipRRect(
      borderRadius: AppRadii.mdAll,
      child: SizedBox.square(
        dimension: size,
        child: image == null
            ? fallback
            : Image.network(
                image,
                fit: BoxFit.cover,
                cacheWidth: (size * ratio).round(),
                errorBuilder: (context, error, stackTrace) => fallback,
              ),
      ),
    );
  }
}

/// Stars and a count, shown only for ratings that really exist.
class RatingLabel extends StatelessWidget {
  const RatingLabel({super.key, required this.rating});

  final RatingSummary rating;

  @override
  Widget build(BuildContext context) {
    final AppLocalizations l10n = context.l10n;
    return Semantics(
      label: l10n.discoveryRatingSemantics(
        rating.average.toStringAsFixed(1),
        rating.count,
      ),
      excludeSemantics: true,
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: <Widget>[
          const Icon(Icons.star_rounded, size: 16, color: AppColors.warning),
          const SizedBox(width: AppSpacing.xxs),
          Text(
            '${rating.average.toStringAsFixed(1)} (${rating.count})',
            style: Theme.of(context).textTheme.labelMedium,
          ),
        ],
      ),
    );
  }
}

/// A business in a list. Queue details appear only when the server reports
/// an open queue: an unknown state is never shown as "open".
class BusinessCard extends StatelessWidget {
  const BusinessCard({super.key, required this.business});

  final PublicBusiness business;

  @override
  Widget build(BuildContext context) {
    final AppLocalizations l10n = context.l10n;
    final TextTheme text = Theme.of(context).textTheme;
    final String language = Localizations.localeOf(context).languageCode;
    final RatingSummary? rating = business.rating;
    final String place = placeLine(
      l10n,
      city: business.city,
      distanceKm: business.distanceKm,
    );
    final String? categoryName = business.category?.nameFor(language);

    return DoryCard(
      padding: const EdgeInsets.all(AppSpacing.md),
      onTap: () => context.push(AppRoutes.customerBusiness(business.slug)),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: <Widget>[
          BusinessLogo(url: business.logoUrl, category: business.category),
          const SizedBox(width: AppSpacing.md),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: <Widget>[
                Text(
                  business.name,
                  style: text.titleMedium,
                  maxLines: 2,
                  overflow: TextOverflow.ellipsis,
                ),
                if (categoryName != null)
                  Text(
                    categoryName,
                    style: text.bodySmall,
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                  ),
                if (place.isNotEmpty)
                  Text(
                    place,
                    style: text.bodySmall,
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                  ),
                const SizedBox(height: AppSpacing.xs),
                Wrap(
                  spacing: AppSpacing.sm,
                  runSpacing: AppSpacing.xs,
                  crossAxisAlignment: WrapCrossAlignment.center,
                  children: <Widget>[
                    ?_queueChip(l10n),
                    if (rating != null) RatingLabel(rating: rating),
                    if (business.branchesCount > 1)
                      Text(
                        l10n.discoveryBranchCount(business.branchesCount),
                        style: text.labelSmall,
                      ),
                  ],
                ),
              ],
            ),
          ),
          FavoriteButton(business: business),
        ],
      ),
    );
  }

  Widget? _queueChip(AppLocalizations l10n) {
    final int? wait = business.lowestWaitMinutes;
    if (business.acceptingQueues > 0 && wait != null) {
      return StatusChip(
        label: waitLabel(l10n, wait),
        tone: StatusTone.positive,
      );
    }
    return business.openQueues > 0
        ? StatusChip(label: l10n.queueStateFull, tone: StatusTone.warning)
        : null;
  }
}

/// A branch in a list.
class BranchCard extends StatelessWidget {
  const BranchCard({super.key, required this.branch, this.showBusiness = true});

  final PublicBranch branch;

  /// Off on the business's own page, where its name is already on screen.
  final bool showBusiness;

  @override
  Widget build(BuildContext context) {
    final AppLocalizations l10n = context.l10n;
    final TextTheme text = Theme.of(context).textTheme;
    final String place = placeLine(
      l10n,
      city: branch.city,
      distanceKm: branch.distanceKm,
    );
    final int? wait = branch.lowestWaitMinutes;

    return DoryCard(
      padding: const EdgeInsets.all(AppSpacing.md),
      onTap: () => context.push(AppRoutes.customerBranch(branch.id)),
      child: Row(
        children: <Widget>[
          if (showBusiness) ...<Widget>[
            BusinessLogo(
              url: branch.business.logoUrl,
              category: branch.business.category,
              size: 44,
            ),
            const SizedBox(width: AppSpacing.md),
          ],
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: <Widget>[
                Text(
                  showBusiness ? branch.business.name : branch.name,
                  style: text.titleSmall,
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                ),
                if (showBusiness)
                  Text(
                    branch.name,
                    style: text.bodySmall,
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                  )
                else if (branch.address != null)
                  Text(
                    branch.address!,
                    style: text.bodySmall,
                    maxLines: 2,
                    overflow: TextOverflow.ellipsis,
                  ),
                if (place.isNotEmpty) Text(place, style: text.bodySmall),
                if (branch.acceptingQueues > 0 && wait != null) ...<Widget>[
                  const SizedBox(height: AppSpacing.xs),
                  StatusChip(
                    label: waitLabel(l10n, wait),
                    tone: StatusTone.positive,
                  ),
                ],
              ],
            ),
          ),
          const Icon(Icons.chevron_right_rounded, color: AppColors.textMuted),
        ],
      ),
    );
  }
}

/// A queue a customer could join now.
class AvailableQueueCard extends StatelessWidget {
  const AvailableQueueCard({super.key, required this.item});

  final AvailableQueue item;

  @override
  Widget build(BuildContext context) {
    final AppLocalizations l10n = context.l10n;
    final TextTheme text = Theme.of(context).textTheme;
    final String place = placeLine(
      l10n,
      city: item.city,
      distanceKm: item.distanceKm,
    );

    return DoryCard(
      padding: const EdgeInsets.all(AppSpacing.md),
      onTap: () => context.push(AppRoutes.customerQueue(item.queue.id)),
      child: Row(
        children: <Widget>[
          BusinessLogo(
            url: item.business.logoUrl,
            category: item.business.category,
            size: 44,
          ),
          const SizedBox(width: AppSpacing.md),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: <Widget>[
                Text(
                  item.serviceName,
                  style: text.titleSmall,
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                ),
                Text(
                  item.business.name,
                  style: text.bodySmall,
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                ),
                if (place.isNotEmpty) Text(place, style: text.bodySmall),
                const SizedBox(height: AppSpacing.xs),
                Wrap(
                  spacing: AppSpacing.sm,
                  crossAxisAlignment: WrapCrossAlignment.center,
                  children: <Widget>[
                    queueStateChip(l10n, item.queue),
                    Text(
                      waitLabel(l10n, item.queue.estimatedMinutes),
                      style: text.labelMedium,
                    ),
                  ],
                ),
              ],
            ),
          ),
          const Icon(Icons.chevron_right_rounded, color: AppColors.textMuted),
        ],
      ),
    );
  }
}

/// Renders a home section's loading, error and data states in place, so one
/// failing section never takes the whole screen down.
class SectionValue<T> extends StatelessWidget {
  const SectionValue({
    super.key,
    required this.value,
    required this.data,
    required this.onRetry,
  });

  final AsyncValue<T> value;
  final Widget Function(T data) data;
  final VoidCallback onRetry;

  @override
  Widget build(BuildContext context) {
    final AppLocalizations l10n = context.l10n;
    if (value.hasValue) {
      return data(value.requireValue);
    }
    if (value.hasError) {
      return DoryCard(
        child: DoryErrorState(
          icon: Icons.cloud_off_rounded,
          title: failureMessage(l10n, value.error!),
          retry: DoryStatusAction(label: l10n.commonRetry, onPressed: onRetry),
        ),
      );
    }
    return const SizedBox(height: 96, child: DoryLoadingIndicator());
  }
}

/// Spaces cards in a column.
class CardColumn extends StatelessWidget {
  const CardColumn({super.key, required this.children});

  final List<Widget> children;

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: <Widget>[
        for (final Widget child in children)
          Padding(
            padding: const EdgeInsets.only(bottom: AppSpacing.sm),
            child: child,
          ),
      ],
    );
  }
}
