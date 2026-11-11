import 'package:dory_app/app/localization/l10n.dart';
import 'package:dory_app/app/router/app_routes.dart';
import 'package:dory_app/app/theme/theme.dart';
import 'package:dory_app/core/errors/app_failure.dart';
import 'package:dory_app/core/widgets/widgets.dart';
import 'package:dory_app/features/subscriptions/domain/subscription.dart';
import 'package:dory_app/features/subscriptions/presentation/providers/subscription_providers.dart';
import 'package:dory_app/features/subscriptions/presentation/widgets/subscription_widgets.dart';
import 'package:dory_app/shared/components/async_value_view.dart';
import 'package:dory_app/shared/components/confirm_dialog.dart';
import 'package:dory_app/shared/components/failure_message.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:material_ui/material_ui.dart';

/// The business's plan, what it uses of it, and (for the owner) what can be
/// changed (docs/SUBSCRIPTIONS.md). The server computes everything shown.
class SubscriptionPage extends ConsumerStatefulWidget {
  const SubscriptionPage({super.key, required this.businessId});

  final String businessId;

  @override
  ConsumerState<SubscriptionPage> createState() => _SubscriptionPageState();
}

class _SubscriptionPageState extends ConsumerState<SubscriptionPage> {
  String get businessId => widget.businessId;

  @override
  void initState() {
    super.initState();
    refreshSubscriptionOnOpen(ref, businessId);
  }

  @override
  Widget build(BuildContext context) {
    final AppLocalizations l10n = context.l10n;
    final AsyncValue<BusinessSubscription> subscription = ref.watch(
      subscriptionProvider(businessId),
    );

    return DoryPageScaffold(
      title: l10n.subscriptionTitle,
      scrollable: false,
      padding: EdgeInsets.zero,
      body: RefreshIndicator(
        onRefresh: () => ref.refresh(subscriptionProvider(businessId).future),
        child: ListView(
          padding: const EdgeInsets.all(AppSpacing.pageGutter),
          children: <Widget>[
            AsyncValueView<BusinessSubscription>(
              value: subscription,
              onRetry: () => ref.invalidate(subscriptionProvider(businessId)),
              data: (value) => _SubscriptionView(
                businessId: businessId,
                subscription: value,
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _SubscriptionView extends ConsumerStatefulWidget {
  const _SubscriptionView({
    required this.businessId,
    required this.subscription,
  });

  final String businessId;
  final BusinessSubscription subscription;

  @override
  ConsumerState<_SubscriptionView> createState() => _SubscriptionViewState();
}

class _SubscriptionViewState extends ConsumerState<_SubscriptionView> {
  bool _busy = false;

  Future<void> _run(Future<void> Function() action, String done) async {
    if (_busy) {
      return;
    }
    setState(() => _busy = true);
    try {
      await action();
      if (mounted) {
        showResultMessage(context, done);
      }
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

  Future<void> _cancel() async {
    final AppLocalizations l10n = context.l10n;
    final BusinessSubscription s = widget.subscription;
    final bool now =
        s.status != SubscriptionStatus.active || s.periodEnd == null;
    final bool confirmed = await confirmAction(
      context,
      title: s.status == SubscriptionStatus.trialing
          ? l10n.subscriptionEndTrial
          : l10n.subscriptionCancelTitle,
      message: now
          ? l10n.subscriptionCancelNowMessage
          : l10n.subscriptionCancelMessage(billingDate(context, s.periodEnd!)),
      confirmLabel: s.status == SubscriptionStatus.trialing
          ? l10n.subscriptionEndTrial
          : l10n.subscriptionCancel,
      destructive: true,
    );
    if (!confirmed || !mounted) {
      return;
    }
    await _run(
      () => ref.read(subscriptionActionsProvider).cancel(widget.businessId),
      l10n.subscriptionCanceled,
    );
  }

  Future<void> _resume() => _run(
    () => ref.read(subscriptionActionsProvider).resume(widget.businessId),
    context.l10n.subscriptionResumed,
  );

  @override
  Widget build(BuildContext context) {
    final AppLocalizations l10n = context.l10n;
    final TextTheme text = Theme.of(context).textTheme;
    final BusinessSubscription s = widget.subscription;
    final String language = languageOf(context);
    final bool pendingChange =
        s.cancelAtPeriodEnd || s.scheduledPlanCode != null;
    final bool canCancel =
        s.canManageBilling &&
        s.billingEnabled &&
        !s.isComplimentary &&
        ((s.isPaid && !s.cancelAtPeriodEnd) ||
            s.status == SubscriptionStatus.trialing);
    final bool overLimit = PlanLimitKind.values.any(s.isOverLimit);

    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: <Widget>[
        DoryCard(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: <Widget>[
              Text(l10n.subscriptionCurrentPlan, style: text.labelLarge),
              const SizedBox(height: AppSpacing.xs),
              Row(
                children: <Widget>[
                  Expanded(
                    child: Text(
                      s.plan.name.of(language),
                      style: text.headlineSmall,
                    ),
                  ),
                  SubscriptionStatusChip(status: s.status),
                ],
              ),
              if (s.plan.description != null) ...<Widget>[
                const SizedBox(height: AppSpacing.xs),
                Text(s.plan.description!.of(language), style: text.bodyMedium),
              ],
              const SizedBox(height: AppSpacing.md),
              ..._statusLines(context, s),
            ],
          ),
        ),
        if (s.status == SubscriptionStatus.pastDue &&
            s.graceEndsAt != null) ...<Widget>[
          const SizedBox(height: AppSpacing.md),
          DoryNotice(
            message: l10n.subscriptionGrace(
              billingDate(context, s.graceEndsAt!),
            ),
            tone: DoryNoticeTone.warning,
          ),
        ],
        if (s.status == SubscriptionStatus.expired &&
            !s.subscribedPlanIsFree) ...<Widget>[
          const SizedBox(height: AppSpacing.md),
          DoryNotice(
            message: l10n.subscriptionExpiredNotice(
              s.subscribedPlanName.of(language),
            ),
            tone: DoryNoticeTone.warning,
          ),
        ],
        const SizedBox(height: AppSpacing.xl),
        DorySectionHeader(title: l10n.subscriptionUsageTitle),
        DoryCard(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: <Widget>[
              for (final PlanLimitKind kind
                  in PlanLimitKind.values) ...<Widget>[
                if (kind != PlanLimitKind.branches)
                  const SizedBox(height: AppSpacing.lg),
                UsageMeter(
                  label: planLimitLabel(l10n, kind),
                  used: s.usage.of(kind),
                  max: s.limits.of(kind),
                ),
              ],
              const SizedBox(height: AppSpacing.md),
              Text(l10n.subscriptionUsageMembersHint, style: text.bodySmall),
            ],
          ),
        ),
        if (overLimit) ...<Widget>[
          const SizedBox(height: AppSpacing.md),
          DoryNotice(message: l10n.subscriptionOverLimit),
        ],
        const SizedBox(height: AppSpacing.xl),
        DorySectionHeader(title: l10n.subscriptionIncludedTitle),
        DoryCard(
          child: Column(
            children: <Widget>[
              PlanFeatureRow(label: l10n.planFeatureEssentials, included: true),
              PlanFeatureRow(
                label: l10n.planFeatureInsights,
                included: s.features.contains(PlanFeatures.insights),
              ),
              PlanFeatureRow(
                label: l10n.planFeatureBranchInsights,
                included: s.features.contains(PlanFeatures.branchInsights),
              ),
            ],
          ),
        ),
        const SizedBox(height: AppSpacing.xl),
        if (s.billingEnabled)
          DoryButton(
            label: l10n.subscriptionSeePlans,
            icon: Icons.workspace_premium_outlined,
            onPressed: () =>
                context.go(AppRoutes.subscriptionPlans(widget.businessId)),
          ),
        if (s.canViewBilling) ...<Widget>[
          const SizedBox(height: AppSpacing.md),
          DoryButton(
            label: l10n.subscriptionHistory,
            icon: Icons.receipt_long_outlined,
            variant: DoryButtonVariant.secondary,
            onPressed: () =>
                context.go(AppRoutes.billingHistory(widget.businessId)),
          ),
        ],
        if (s.canManageBilling &&
            pendingChange &&
            s.isPaid &&
            s.status == SubscriptionStatus.active) ...<Widget>[
          const SizedBox(height: AppSpacing.md),
          DoryButton(
            label: l10n.subscriptionResume,
            icon: Icons.replay_rounded,
            variant: DoryButtonVariant.secondary,
            isLoading: _busy,
            onPressed: _resume,
          ),
        ],
        if (canCancel) ...<Widget>[
          const SizedBox(height: AppSpacing.md),
          DoryButton(
            label: s.status == SubscriptionStatus.trialing
                ? l10n.subscriptionEndTrial
                : l10n.subscriptionCancel,
            variant: DoryButtonVariant.tertiary,
            isLoading: _busy,
            onPressed: _cancel,
          ),
        ],
        if (!s.canManageBilling && s.billingEnabled) ...<Widget>[
          const SizedBox(height: AppSpacing.md),
          Text(
            l10n.subscriptionOwnerOnly,
            style: text.bodySmall,
            textAlign: TextAlign.center,
          ),
        ],
      ],
    );
  }

  List<Widget> _statusLines(BuildContext context, BusinessSubscription s) {
    final AppLocalizations l10n = context.l10n;
    final TextTheme text = Theme.of(context).textTheme;
    final String language = languageOf(context);
    Widget line(String value, {TextStyle? style}) => Padding(
      padding: const EdgeInsets.only(top: AppSpacing.xs),
      child: Text(value, style: style ?? text.bodyMedium),
    );

    if (!s.billingEnabled) {
      return <Widget>[line(l10n.subscriptionBillingOff)];
    }
    return <Widget>[
      if (s.status == SubscriptionStatus.trialing &&
          s.trialEndsAt != null) ...<Widget>[
        line(l10n.subscriptionTrialEnds(billingDate(context, s.trialEndsAt!))),
        line(
          l10n.subscriptionDaysLeft(daysUntil(s.trialEndsAt!)),
          style: text.titleSmall?.copyWith(color: AppColors.primary),
        ),
      ],
      if (s.isComplimentary) line(l10n.subscriptionComplimentary),
      if (s.isPaid && s.amount != null && s.currency != null)
        line(
          s.billingPeriod == BillingPeriod.yearly
              ? l10n.plansPerYear(money(s.amount!, s.currency!))
              : l10n.plansPerMonth(money(s.amount!, s.currency!)),
          style: text.titleSmall,
        ),
      if (s.isPaid &&
          s.periodEnd != null &&
          s.status == SubscriptionStatus.active) ...<Widget>[
        if (s.cancelAtPeriodEnd)
          line(l10n.subscriptionEndsOn(billingDate(context, s.periodEnd!)))
        else ...<Widget>[
          line(l10n.subscriptionPaidUntil(billingDate(context, s.periodEnd!))),
          line(l10n.subscriptionRenewalNote, style: text.bodySmall),
        ],
        if (s.scheduledPlanName != null)
          line(
            l10n.subscriptionScheduled(
              s.scheduledPlanName!.of(language),
              billingDate(context, s.periodEnd!),
            ),
          ),
      ],
    ];
  }
}
