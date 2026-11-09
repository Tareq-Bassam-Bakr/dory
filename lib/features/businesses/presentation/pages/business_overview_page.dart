import 'package:dory_app/app/localization/l10n.dart';
import 'package:dory_app/app/router/app_routes.dart';
import 'package:dory_app/app/theme/theme.dart';
import 'package:dory_app/core/errors/app_failure.dart';
import 'package:dory_app/core/widgets/widgets.dart';
import 'package:dory_app/features/business_dashboard/presentation/widgets/queue_summary_grid.dart';
import 'package:dory_app/features/businesses/domain/entities/business.dart';
import 'package:dory_app/features/businesses/domain/entities/business_draft.dart';
import 'package:dory_app/features/businesses/presentation/providers/business_providers.dart';
import 'package:dory_app/features/businesses/presentation/widgets/business_labels.dart';
import 'package:dory_app/shared/components/async_value_view.dart';
import 'package:dory_app/shared/components/category_icon.dart';
import 'package:dory_app/shared/components/confirm_dialog.dart';
import 'package:dory_app/shared/components/failure_message.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:material_ui/material_ui.dart';

/// One business at a glance: status, profile completion, statistics and
/// the management entry points the viewer's role allows.
class BusinessOverviewPage extends ConsumerWidget {
  const BusinessOverviewPage({super.key, required this.businessId});

  final String businessId;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final AsyncValue<Business> business = ref.watch(
      businessProvider(businessId),
    );
    return DoryPageScaffold(
      title: business.value?.name ?? context.l10n.myBusinessesTitle,
      body: AsyncValueView<Business>(
        value: business,
        onRetry: () => ref.invalidate(businessProvider(businessId)),
        data: (value) => _Overview(business: value),
      ),
    );
  }
}

class _Overview extends ConsumerStatefulWidget {
  const _Overview({required this.business});

  final Business business;

  @override
  ConsumerState<_Overview> createState() => _OverviewState();
}

class _OverviewState extends ConsumerState<_Overview> {
  bool _busy = false;

  Future<void> _run(Future<void> Function() action) async {
    if (_busy) {
      return;
    }
    setState(() => _busy = true);
    try {
      await action();
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

  Future<void> _toggleStatus(bool active) => _run(() async {
    await ref
        .read(businessActionsProvider)
        .update(widget.business.id, BusinessProfileChanges(isActive: active));
  });

  Future<void> _delete() async {
    final AppLocalizations l10n = context.l10n;
    final bool confirmed = await confirmAction(
      context,
      title: l10n.businessDeleteConfirmTitle(widget.business.name),
      message: l10n.businessDeleteConfirmMessage,
      confirmLabel: l10n.commonDelete,
      destructive: true,
    );
    if (!confirmed || !mounted) {
      return;
    }
    await _run(() async {
      await ref.read(businessActionsProvider).delete(widget.business.id);
      if (mounted) {
        showResultMessage(context, l10n.businessDeleted);
        context.go(AppRoutes.businessDashboard);
      }
    });
  }

  @override
  Widget build(BuildContext context) {
    final AppLocalizations l10n = context.l10n;
    final TextTheme text = Theme.of(context).textTheme;
    final Business business = widget.business;
    final BusinessPermissions can = business.permissions;
    final String language = Localizations.localeOf(context).languageCode;
    final BusinessStats? stats = business.stats;

    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: <Widget>[
        DoryCard(
          child: Row(
            children: <Widget>[
              CircleAvatar(
                radius: 28,
                backgroundColor: AppColors.primaryContainer,
                foregroundColor: AppColors.primary,
                child: Icon(
                  categoryIcon(business.category?.icon ?? 'other'),
                  size: 28,
                ),
              ),
              const SizedBox(width: AppSpacing.md),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: <Widget>[
                    Text(business.name, style: text.titleLarge),
                    if (business.category != null)
                      Text(
                        business.category!.nameFor(language),
                        style: text.bodyMedium,
                      ),
                    const SizedBox(height: AppSpacing.xs),
                    Wrap(
                      spacing: AppSpacing.sm,
                      runSpacing: AppSpacing.xs,
                      children: <Widget>[
                        businessStatusChip(l10n, business.status),
                        if (business.role != null)
                          Text(
                            l10n.businessYourRole(
                              roleLabel(l10n, business.role!),
                            ),
                            style: text.labelMedium,
                          ),
                      ],
                    ),
                  ],
                ),
              ),
            ],
          ),
        ),
        if (business.isSuspended) ...<Widget>[
          const SizedBox(height: AppSpacing.lg),
          DoryNotice(
            message: l10n.businessSuspendedNotice,
            tone: DoryNoticeTone.warning,
          ),
        ],
        if (stats != null) ...<Widget>[
          const SizedBox(height: AppSpacing.lg),
          Row(
            children: <Widget>[
              Expanded(
                child: DoryMetricTile(
                  noValueLabel: l10n.dashboardMetricNoValue,
                  label: l10n.businessBranches,
                  value: '${stats.branches}',
                  icon: Icons.store_mall_directory_outlined,
                ),
              ),
              const SizedBox(width: AppSpacing.md),
              Expanded(
                child: DoryMetricTile(
                  noValueLabel: l10n.dashboardMetricNoValue,
                  label: l10n.servicesTitle,
                  value: '${stats.services}',
                  icon: Icons.design_services_outlined,
                ),
              ),
              const SizedBox(width: AppSpacing.md),
              Expanded(
                child: DoryMetricTile(
                  noValueLabel: l10n.dashboardMetricNoValue,
                  label: l10n.businessTeam,
                  value: '${stats.members}',
                  icon: Icons.groups_outlined,
                ),
              ),
            ],
          ),
        ],
        if (business.profile != null) ...<Widget>[
          const SizedBox(height: AppSpacing.xl),
          _CompletionCard(profile: business.profile!),
        ],
        const SizedBox(height: AppSpacing.xl),
        DorySectionHeader(title: l10n.businessManageSection),
        DoryCard(
          padding: EdgeInsets.zero,
          child: Column(
            children: <Widget>[
              if (can.update)
                ListTile(
                  leading: const Icon(Icons.edit_outlined),
                  title: Text(l10n.businessEditProfile),
                  trailing: const Icon(Icons.chevron_right_rounded),
                  onTap: () => context.go(AppRoutes.businessEdit(business.id)),
                ),
              ListTile(
                leading: const Icon(Icons.store_mall_directory_outlined),
                title: Text(l10n.businessBranches),
                trailing: const Icon(Icons.chevron_right_rounded),
                onTap: () => context.go(AppRoutes.branches(business.id)),
              ),
              if (can.viewInsights)
                ListTile(
                  leading: const Icon(Icons.insights_outlined),
                  title: Text(l10n.insightsEntry),
                  trailing: const Icon(Icons.chevron_right_rounded),
                  onTap: () =>
                      context.go(AppRoutes.businessInsights(business.id)),
                ),
              if (can.viewMembers)
                ListTile(
                  leading: const Icon(Icons.groups_outlined),
                  title: Text(l10n.businessTeam),
                  trailing: const Icon(Icons.chevron_right_rounded),
                  onTap: () => context.go(AppRoutes.team(business.id)),
                ),
              if (can.changeStatus)
                SwitchListTile(
                  secondary: const Icon(Icons.storefront_outlined),
                  title: Text(l10n.businessStatusTitle),
                  subtitle: Text(
                    business.status == BusinessStatus.active
                        ? l10n.businessStatusActiveHint
                        : l10n.businessStatusInactiveHint,
                  ),
                  value: business.status == BusinessStatus.active,
                  onChanged: _busy ? null : _toggleStatus,
                ),
            ],
          ),
        ),
        const SizedBox(height: AppSpacing.xl),
        DorySectionHeader(title: l10n.dashboardQueueSummaryTitle),
        const QueueSummaryGrid(),
        const SizedBox(height: AppSpacing.sm),
        Text(l10n.dashboardQueueSummaryEmpty, style: text.bodySmall),
        if (can.delete) ...<Widget>[
          const SizedBox(height: AppSpacing.xxl),
          DorySectionHeader(title: l10n.businessDangerZone),
          DoryButton(
            label: l10n.businessDelete,
            icon: Icons.delete_outline_rounded,
            variant: DoryButtonVariant.tertiary,
            isLoading: _busy,
            onPressed: _delete,
          ),
        ],
      ],
    );
  }
}

class _CompletionCard extends StatelessWidget {
  const _CompletionCard({required this.profile});

  final ProfileCompletion profile;

  @override
  Widget build(BuildContext context) {
    final AppLocalizations l10n = context.l10n;
    final TextTheme text = Theme.of(context).textTheme;
    String step(String key) => switch (key) {
      'basics' => l10n.businessMissingBasics,
      'description' => l10n.businessMissingDescription,
      'contact' => l10n.businessMissingContact,
      'branch' => l10n.businessMissingBranch,
      'service' => l10n.businessMissingService,
      _ => key,
    };

    return DoryCard(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: <Widget>[
          Text(
            profile.isComplete
                ? l10n.businessCompletionDone
                : l10n.businessCompletionTitle,
            style: text.titleMedium,
          ),
          const SizedBox(height: AppSpacing.sm),
          Text(
            l10n.businessProfileCompletion(profile.percentage),
            style: text.labelMedium,
          ),
          const SizedBox(height: AppSpacing.xs),
          ClipRRect(
            borderRadius: AppRadii.fullAll,
            child: LinearProgressIndicator(
              value: profile.percentage / 100,
              minHeight: 8,
            ),
          ),
          for (final String missing in profile.missing)
            Padding(
              padding: const EdgeInsets.only(top: AppSpacing.sm),
              child: Row(
                children: <Widget>[
                  const Icon(
                    Icons.radio_button_unchecked_rounded,
                    size: 18,
                    color: AppColors.textMuted,
                  ),
                  const SizedBox(width: AppSpacing.sm),
                  Expanded(child: Text(step(missing), style: text.bodyMedium)),
                ],
              ),
            ),
        ],
      ),
    );
  }
}
