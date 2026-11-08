import 'package:dory_app/app/localization/l10n.dart';
import 'package:dory_app/app/router/app_routes.dart';
import 'package:dory_app/app/theme/theme.dart';
import 'package:dory_app/core/widgets/widgets.dart';
import 'package:dory_app/features/business_dashboard/presentation/widgets/business_setup_card.dart';
import 'package:dory_app/features/businesses/domain/entities/business.dart';
import 'package:dory_app/features/businesses/presentation/providers/business_providers.dart';
import 'package:dory_app/features/businesses/presentation/widgets/business_labels.dart';
import 'package:dory_app/features/notifications/presentation/widgets/notification_widgets.dart';
import 'package:dory_app/features/team/presentation/widgets/join_invitation_dialog.dart';
import 'package:dory_app/shared/components/async_value_view.dart';
import 'package:dory_app/shared/components/category_icon.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:material_ui/material_ui.dart';

/// "My businesses": the business workspace home. Lists every business the
/// user works in, with their role and profile completion, and starts
/// onboarding or opens an invitation.
class BusinessDashboardPage extends ConsumerWidget {
  const BusinessDashboardPage({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final AppLocalizations l10n = context.l10n;
    final AsyncValue<List<Business>> businesses = ref.watch(
      myBusinessesProvider,
    );

    return DoryPageScaffold(
      title: l10n.myBusinessesTitle,
      actions: <Widget>[
        const NotificationBell(),
        IconButton(
          tooltip: l10n.myBusinessesJoin,
          icon: const Icon(Icons.mark_email_unread_outlined),
          onPressed: () => showJoinInvitationDialog(context),
        ),
      ],
      body: RefreshIndicator(
        onRefresh: () => ref.refresh(myBusinessesProvider.future),
        child: AsyncValueView<List<Business>>(
          value: businesses,
          onRetry: () => ref.invalidate(myBusinessesProvider),
          data: (items) => items.isEmpty
              ? Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: <Widget>[
                    BusinessSetupCard(
                      onRegister: () => context.push(AppRoutes.businessCreate),
                    ),
                    const SizedBox(height: AppSpacing.lg),
                    DoryButton(
                      label: l10n.myBusinessesJoin,
                      icon: Icons.mark_email_unread_outlined,
                      variant: DoryButtonVariant.tertiary,
                      onPressed: () => showJoinInvitationDialog(context),
                    ),
                  ],
                )
              : Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: <Widget>[
                    for (final Business business in items) ...<Widget>[
                      _BusinessTile(business: business),
                      const SizedBox(height: AppSpacing.md),
                    ],
                    const SizedBox(height: AppSpacing.sm),
                    DoryButton(
                      label: l10n.myBusinessesCreate,
                      icon: Icons.add_rounded,
                      variant: DoryButtonVariant.secondary,
                      onPressed: () => context.push(AppRoutes.businessCreate),
                    ),
                  ],
                ),
        ),
      ),
    );
  }
}

class _BusinessTile extends StatelessWidget {
  const _BusinessTile({required this.business});

  final Business business;

  @override
  Widget build(BuildContext context) {
    final AppLocalizations l10n = context.l10n;
    final TextTheme text = Theme.of(context).textTheme;
    final int completion = business.profile?.percentage ?? 0;

    return DoryCard(
      onTap: () => context.go(AppRoutes.business(business.id)),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: <Widget>[
          Row(
            children: <Widget>[
              CircleAvatar(
                backgroundColor: AppColors.primaryContainer,
                foregroundColor: AppColors.primary,
                child: Icon(categoryIcon(business.category?.icon ?? 'other')),
              ),
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
                    if (business.role != null)
                      Text(
                        roleLabel(l10n, business.role!),
                        style: text.bodySmall,
                      ),
                  ],
                ),
              ),
              businessStatusChip(l10n, business.status),
            ],
          ),
          if (business.profile != null &&
              !business.profile!.isComplete) ...<Widget>[
            const SizedBox(height: AppSpacing.md),
            Text(
              l10n.businessProfileCompletion(completion),
              style: text.labelMedium,
            ),
            const SizedBox(height: AppSpacing.xs),
            ClipRRect(
              borderRadius: AppRadii.fullAll,
              child: LinearProgressIndicator(
                value: completion / 100,
                minHeight: 6,
              ),
            ),
          ],
        ],
      ),
    );
  }
}
