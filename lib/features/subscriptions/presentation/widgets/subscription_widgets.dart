import 'package:dory_app/app/localization/l10n.dart';
import 'package:dory_app/app/router/app_routes.dart';
import 'package:dory_app/app/theme/theme.dart';
import 'package:dory_app/core/errors/app_failure.dart';
import 'package:dory_app/core/widgets/widgets.dart';
import 'package:dory_app/features/subscriptions/domain/subscription.dart';
import 'package:dory_app/shared/components/failure_message.dart';
import 'package:go_router/go_router.dart';
import 'package:intl/intl.dart';
import 'package:material_ui/material_ui.dart';

/// Shared pieces of the plan and billing screens (docs/SUBSCRIPTIONS.md).

String languageOf(BuildContext context) =>
    Localizations.localeOf(context).languageCode;

/// A date in the reader's language, in local time.
String billingDate(BuildContext context, DateTime date) =>
    DateFormat.yMMMd(languageOf(context)).format(date.toLocal());

/// "15.00 USD", kept left-to-right inside Arabic text.
String money(String amount, String currency) => '\u2066$amount $currency\u2069';

/// Whole days from now until [date], never negative.
int daysUntil(DateTime date, {DateTime? now}) {
  final Duration left = date.difference(now ?? DateTime.now());
  return left.isNegative ? 0 : (left.inHours / 24).ceil();
}

String subscriptionStatusLabel(
  AppLocalizations l10n,
  SubscriptionStatus status,
) => switch (status) {
  SubscriptionStatus.trialing => l10n.subscriptionStatusTrialing,
  SubscriptionStatus.active => l10n.subscriptionStatusActive,
  SubscriptionStatus.pastDue => l10n.subscriptionStatusPastDue,
  SubscriptionStatus.expired => l10n.subscriptionStatusExpired,
};

String planLimitLabel(AppLocalizations l10n, PlanLimitKind kind) =>
    switch (kind) {
      PlanLimitKind.branches => l10n.planLimitBranchesLabel,
      PlanLimitKind.queues => l10n.planLimitQueuesLabel,
      PlanLimitKind.members => l10n.planLimitMembersLabel,
    };

String planFeatureLabel(AppLocalizations l10n, String feature) =>
    switch (feature) {
      PlanFeatures.insights => l10n.planFeatureInsights,
      PlanFeatures.branchInsights => l10n.planFeatureBranchInsights,
      _ => feature,
    };

class SubscriptionStatusChip extends StatelessWidget {
  const SubscriptionStatusChip({super.key, required this.status});

  final SubscriptionStatus status;

  @override
  Widget build(BuildContext context) {
    final (Color background, Color foreground) = switch (status) {
      SubscriptionStatus.active => (
        AppColors.successContainer,
        AppColors.success,
      ),
      SubscriptionStatus.trialing => (
        AppColors.primaryContainer,
        AppColors.onPrimaryContainer,
      ),
      SubscriptionStatus.pastDue => (
        AppColors.warningContainer,
        AppColors.warning,
      ),
      SubscriptionStatus.expired => (
        AppColors.surfaceStrong,
        AppColors.textSecondary,
      ),
    };
    return _Pill(
      label: subscriptionStatusLabel(context.l10n, status),
      background: background,
      foreground: foreground,
    );
  }
}

class _Pill extends StatelessWidget {
  const _Pill({
    required this.label,
    required this.background,
    required this.foreground,
  });

  final String label;
  final Color background;
  final Color foreground;

  @override
  Widget build(BuildContext context) {
    return DecoratedBox(
      decoration: BoxDecoration(
        color: background,
        borderRadius: BorderRadius.circular(999),
      ),
      child: Padding(
        padding: const EdgeInsets.symmetric(
          horizontal: AppSpacing.md,
          vertical: AppSpacing.xs,
        ),
        child: Text(
          label,
          style: Theme.of(context).textTheme.labelMedium?.copyWith(
            color: foreground,
            fontWeight: FontWeight.w600,
          ),
        ),
      ),
    );
  }
}

/// A small badge, e.g. "Current plan" on a plan card.
class PlanBadge extends StatelessWidget {
  const PlanBadge({super.key, required this.label});

  final String label;

  @override
  Widget build(BuildContext context) => _Pill(
    label: label,
    background: AppColors.primaryContainer,
    foreground: AppColors.onPrimaryContainer,
  );
}

/// How much of one limit the business uses.
class UsageMeter extends StatelessWidget {
  const UsageMeter({
    super.key,
    required this.label,
    required this.used,
    required this.max,
  });

  final String label;
  final int used;

  /// Null: unlimited.
  final int? max;

  @override
  Widget build(BuildContext context) {
    final AppLocalizations l10n = context.l10n;
    final TextTheme text = Theme.of(context).textTheme;
    final int? limit = max;
    final bool over = limit != null && used > limit;
    final bool full = limit != null && used >= limit;
    final Color color = over
        ? AppColors.warning
        : full
        ? AppColors.secondary
        : AppColors.primary;

    return Semantics(
      label: label,
      value: limit == null
          ? l10n.subscriptionUsageUnlimited(used)
          : l10n.subscriptionUsageOf(used, limit),
      child: ExcludeSemantics(
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: <Widget>[
            Row(
              children: <Widget>[
                Expanded(child: Text(label, style: text.bodyMedium)),
                Text(
                  limit == null
                      ? l10n.subscriptionUsageUnlimited(used)
                      : l10n.subscriptionUsageOf(used, limit),
                  style: text.labelLarge?.copyWith(
                    color: over ? AppColors.warning : null,
                  ),
                ),
              ],
            ),
            const SizedBox(height: AppSpacing.xs),
            ClipRRect(
              borderRadius: BorderRadius.circular(999),
              child: LinearProgressIndicator(
                minHeight: 8,
                value: limit == null || limit == 0
                    ? 0
                    : (used / limit).clamp(0, 1).toDouble(),
                color: color,
                backgroundColor: AppColors.surfaceStrong,
              ),
            ),
          ],
        ),
      ),
    );
  }
}

/// A line of what a plan includes, with a check or a dash.
class PlanFeatureRow extends StatelessWidget {
  const PlanFeatureRow({
    super.key,
    required this.label,
    required this.included,
  });

  final String label;
  final bool included;

  @override
  Widget build(BuildContext context) {
    final AppLocalizations l10n = context.l10n;
    return Semantics(
      label: '$label: ${included ? l10n.plansIncluded : l10n.plansNotIncluded}',
      child: ExcludeSemantics(
        child: Padding(
          padding: const EdgeInsets.symmetric(vertical: AppSpacing.xs),
          child: Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: <Widget>[
              Icon(
                included
                    ? Icons.check_circle_rounded
                    : Icons.remove_circle_outline_rounded,
                size: 20,
                color: included ? AppColors.success : AppColors.textMuted,
              ),
              const SizedBox(width: AppSpacing.sm),
              Expanded(
                child: Text(
                  label,
                  style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                    color: included ? null : AppColors.textMuted,
                  ),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

/// Explains a reached plan limit and offers the plans. The server refused
/// the action; nothing was added.
Future<void> showPlanLimitDialog(
  BuildContext context,
  AppFailure failure, {
  required String businessId,
}) {
  final AppLocalizations l10n = context.l10n;
  return showDialog<void>(
    context: context,
    builder: (dialogContext) => AlertDialog(
      icon: const Icon(
        Icons.workspace_premium_outlined,
        color: AppColors.primary,
      ),
      title: Text(l10n.planLimitTitle),
      content: Column(
        mainAxisSize: MainAxisSize.min,
        crossAxisAlignment: CrossAxisAlignment.start,
        children: <Widget>[
          Text(planLimitMessage(l10n, failure)),
          const SizedBox(height: AppSpacing.md),
          Text(
            l10n.planLimitKeepsWorking,
            style: Theme.of(dialogContext).textTheme.bodySmall,
          ),
        ],
      ),
      actions: <Widget>[
        TextButton(
          onPressed: () => Navigator.of(dialogContext).pop(),
          child: Text(l10n.planLimitNotNow),
        ),
        FilledButton(
          onPressed: () {
            Navigator.of(dialogContext).pop();
            context.push(AppRoutes.subscriptionPlans(businessId));
          },
          child: Text(l10n.subscriptionSeePlans),
        ),
      ],
    ),
  );
}

/// Shown instead of a feature the plan does not include.
class PlanLockedView extends StatelessWidget {
  const PlanLockedView({
    super.key,
    required this.businessId,
    required this.message,
  });

  final String businessId;
  final String message;

  @override
  Widget build(BuildContext context) {
    final AppLocalizations l10n = context.l10n;
    final TextTheme text = Theme.of(context).textTheme;
    return DoryCard(
      child: Column(
        children: <Widget>[
          const CircleAvatar(
            radius: 28,
            backgroundColor: AppColors.primaryContainer,
            foregroundColor: AppColors.primary,
            child: Icon(Icons.lock_outline_rounded, size: 28),
          ),
          const SizedBox(height: AppSpacing.md),
          Text(
            l10n.planLockedTitle,
            style: text.titleMedium,
            textAlign: TextAlign.center,
          ),
          const SizedBox(height: AppSpacing.sm),
          Text(message, style: text.bodyMedium, textAlign: TextAlign.center),
          const SizedBox(height: AppSpacing.lg),
          DoryButton(
            label: l10n.subscriptionSeePlans,
            icon: Icons.workspace_premium_outlined,
            onPressed: () =>
                context.push(AppRoutes.subscriptionPlans(businessId)),
          ),
        ],
      ),
    );
  }
}

/// A short line on the business overview when the plan needs attention:
/// a trial running out, a payment due, or a plan that ended.
class SubscriptionAttention extends StatelessWidget {
  const SubscriptionAttention({
    super.key,
    required this.businessId,
    required this.subscription,
  });

  final String businessId;
  final BusinessSubscription subscription;

  @override
  Widget build(BuildContext context) {
    final AppLocalizations l10n = context.l10n;
    final BusinessSubscription s = subscription;
    final String language = languageOf(context);
    final (String message, DoryNoticeTone tone)? notice = switch (s.status) {
      _ when !s.billingEnabled => null,
      SubscriptionStatus.trialing when s.trialEndsAt != null => (
        l10n.subscriptionBannerTrial(
          daysUntil(s.trialEndsAt!),
          s.plan.name.of(language),
        ),
        DoryNoticeTone.info,
      ),
      SubscriptionStatus.pastDue when s.graceEndsAt != null => (
        l10n.subscriptionBannerPastDue(billingDate(context, s.graceEndsAt!)),
        DoryNoticeTone.warning,
      ),
      SubscriptionStatus.expired when !s.subscribedPlanIsFree => (
        l10n.subscriptionBannerExpired(s.subscribedPlanName.of(language)),
        DoryNoticeTone.warning,
      ),
      _ => null,
    };
    if (notice == null) {
      return const SizedBox.shrink();
    }
    return Padding(
      padding: const EdgeInsets.only(top: AppSpacing.lg),
      child: InkWell(
        borderRadius: BorderRadius.circular(12),
        onTap: () => context.go(AppRoutes.subscription(businessId)),
        child: DoryNotice(message: notice.$1, tone: notice.$2),
      ),
    );
  }
}
