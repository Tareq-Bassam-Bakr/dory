import 'dart:async';

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
import 'package:url_launcher/url_launcher.dart';

/// Opens a payment page outside the app. Replaced in tests.
typedef PaymentPageLauncher = Future<bool> Function(Uri url);

final Provider<PaymentPageLauncher> paymentPageLauncherProvider =
    Provider<PaymentPageLauncher>(
      (ref) =>
          (url) => launchUrl(url, mode: LaunchMode.externalApplication),
      name: 'paymentPageLauncherProvider',
    );

/// The plans side by side, monthly or yearly, and what the owner can do with
/// each: buy or renew it, or switch to it at the end of the paid period
/// (docs/SUBSCRIPTIONS.md). Customers never need a plan.
class PlansPage extends ConsumerStatefulWidget {
  const PlansPage({super.key, required this.businessId});

  final String businessId;

  @override
  ConsumerState<PlansPage> createState() => _PlansPageState();
}

class _PlansPageState extends ConsumerState<PlansPage> {
  BillingPeriod? _period;
  String? _busyPlan;

  @override
  void initState() {
    super.initState();
    refreshSubscriptionOnOpen(ref, widget.businessId);
  }

  @override
  Widget build(BuildContext context) {
    final AppLocalizations l10n = context.l10n;
    final AsyncValue<PlanCatalog> catalog = ref.watch(planCatalogProvider);
    final AsyncValue<BusinessSubscription> subscription = ref.watch(
      subscriptionProvider(widget.businessId),
    );

    return DoryPageScaffold(
      title: l10n.plansTitle,
      scrollable: false,
      padding: EdgeInsets.zero,
      body: RefreshIndicator(
        onRefresh: () async {
          ref
            ..invalidate(planCatalogProvider)
            ..invalidate(subscriptionProvider(widget.businessId));
          await ref.read(planCatalogProvider.future);
        },
        child: ListView(
          padding: const EdgeInsets.all(AppSpacing.pageGutter),
          children: <Widget>[
            AsyncValueView<PlanCatalog>(
              value: catalog,
              onRetry: () => ref.invalidate(planCatalogProvider),
              data: (plans) => AsyncValueView<BusinessSubscription>(
                value: subscription,
                onRetry: () =>
                    ref.invalidate(subscriptionProvider(widget.businessId)),
                data: (current) => _content(context, plans, current),
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _content(
    BuildContext context,
    PlanCatalog catalog,
    BusinessSubscription current,
  ) {
    final AppLocalizations l10n = context.l10n;
    final TextTheme text = Theme.of(context).textTheme;
    final BillingPeriod period =
        _period ?? current.billingPeriod ?? BillingPeriod.monthly;
    final int? saving = _yearlySaving(catalog);
    final SubscriptionPlan? trial = catalog.trialPlan == null
        ? null
        : catalog.plan(catalog.trialPlan!);

    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: <Widget>[
        SegmentedButton<BillingPeriod>(
          segments: <ButtonSegment<BillingPeriod>>[
            ButtonSegment<BillingPeriod>(
              value: BillingPeriod.monthly,
              label: Text(l10n.plansMonthly),
            ),
            ButtonSegment<BillingPeriod>(
              value: BillingPeriod.yearly,
              label: Text(l10n.plansYearly),
            ),
          ],
          selected: <BillingPeriod>{period},
          onSelectionChanged: (value) => setState(() => _period = value.first),
        ),
        if (saving != null && saving > 0) ...<Widget>[
          const SizedBox(height: AppSpacing.sm),
          Text(
            l10n.plansYearlySave(saving),
            style: text.bodySmall?.copyWith(color: AppColors.success),
            textAlign: TextAlign.center,
          ),
        ],
        const SizedBox(height: AppSpacing.lg),
        for (final SubscriptionPlan plan in catalog.plans) ...<Widget>[
          _PlanCard(
            plan: plan,
            period: period,
            currency: catalog.defaultCurrency,
            current: current,
            action: _actionFor(plan, period, catalog, current),
            busy: _busyPlan == plan.code,
            onAction: (action) =>
                _onAction(action, plan, period, catalog, current),
          ),
          const SizedBox(height: AppSpacing.md),
        ],
        if (!current.canManageBilling)
          DoryNotice(message: l10n.subscriptionOwnerOnly),
        const SizedBox(height: AppSpacing.lg),
        DorySectionHeader(title: l10n.plansCompareTitle),
        _ComparisonTable(plans: catalog.plans),
        const SizedBox(height: AppSpacing.lg),
        if (trial != null && catalog.trialDays > 0)
          Text(
            l10n.plansTrialNote(
              catalog.trialDays,
              trial.name.of(languageOf(context)),
            ),
            style: text.bodySmall,
            textAlign: TextAlign.center,
          ),
        const SizedBox(height: AppSpacing.sm),
        Text(
          l10n.plansCustomersFree,
          style: text.bodySmall,
          textAlign: TextAlign.center,
        ),
      ],
    );
  }

  /// The biggest yearly saving of any plan, in percent.
  int? _yearlySaving(PlanCatalog catalog) {
    int? best;
    for (final SubscriptionPlan plan in catalog.plans) {
      final PlanPrice? monthly = plan.priceFor(
        BillingPeriod.monthly,
        catalog.defaultCurrency,
      );
      final PlanPrice? yearly = plan.priceFor(
        BillingPeriod.yearly,
        catalog.defaultCurrency,
      );
      if (monthly == null || yearly == null || monthly.amountMinor <= 0) {
        continue;
      }
      final int percent =
          (100 - yearly.amountMinor * 100 / (monthly.amountMinor * 12)).floor();
      if (best == null || percent > best) {
        best = percent;
      }
    }
    return best;
  }

  _PlanAction _actionFor(
    SubscriptionPlan plan,
    BillingPeriod period,
    PlanCatalog catalog,
    BusinessSubscription s,
  ) {
    if (!s.canManageBilling || !s.billingEnabled) {
      return s.plan.code == plan.code ? _PlanAction.current : _PlanAction.none;
    }
    final int rank = plan.isFree ? -1 : plan.sortOrder;
    final int currentRank =
        s.subscribedPlanIsFree || s.status == SubscriptionStatus.expired
        ? -1
        : (catalog.plan(s.subscribedPlanCode)?.sortOrder ?? -1);
    final bool runningPaid = s.isPaid && s.status == SubscriptionStatus.active;

    if (plan.isFree) {
      if (s.status == SubscriptionStatus.trialing ||
          (s.isPaid && !s.cancelAtPeriodEnd)) {
        return _PlanAction.switchToFree;
      }
      return s.plan.isFree ? _PlanAction.current : _PlanAction.none;
    }
    if (plan.priceFor(period, catalog.defaultCurrency) == null) {
      return _PlanAction.unavailable;
    }
    if (s.isPaid && plan.code == s.subscribedPlanCode) {
      return _PlanAction.renew;
    }
    if (runningPaid && rank < currentRank) {
      return s.scheduledPlanCode == plan.code
          ? _PlanAction.none
          : _PlanAction.switchLater;
    }
    return _PlanAction.choose;
  }

  Future<void> _onAction(
    _PlanAction action,
    SubscriptionPlan plan,
    BillingPeriod period,
    PlanCatalog catalog,
    BusinessSubscription s,
  ) async {
    final AppLocalizations l10n = context.l10n;
    final String language = languageOf(context);
    switch (action) {
      case _PlanAction.choose || _PlanAction.renew:
        if (!catalog.onlinePayments) {
          final PlanPrice price = plan.priceFor(
            period,
            catalog.defaultCurrency,
          )!;
          await showDialog<void>(
            context: context,
            builder: (dialogContext) => AlertDialog(
              icon: const Icon(
                Icons.account_balance_outlined,
                color: AppColors.primary,
              ),
              title: Text(l10n.paymentOfflineTitle),
              content: Text(
                l10n.paymentOfflineMessage(
                  plan.name.of(language),
                  period == BillingPeriod.yearly
                      ? l10n.plansPerYear(money(price.amount, price.currency))
                      : l10n.plansPerMonth(money(price.amount, price.currency)),
                ),
              ),
              actions: <Widget>[
                FilledButton(
                  onPressed: () => Navigator.of(dialogContext).pop(),
                  child: Text(
                    MaterialLocalizations.of(dialogContext).okButtonLabel,
                  ),
                ),
              ],
            ),
          );
          return;
        }
        await _pay(plan, period);
      case _PlanAction.switchLater:
        final bool confirmed = await confirmAction(
          context,
          title: l10n.plansDowngradeTitle(plan.name.of(language)),
          message: l10n.plansDowngradeMessage(
            plan.name.of(language),
            billingDate(context, s.periodEnd!),
          ),
          confirmLabel: l10n.plansSwitchLater(plan.name.of(language)),
        );
        if (confirmed) {
          await _change(
            plan.code,
            () => ref
                .read(subscriptionActionsProvider)
                .downgrade(widget.businessId, plan: plan.code, period: period),
            l10n.plansDowngradeScheduled,
          );
        }
      case _PlanAction.switchToFree:
        final bool now =
            s.status != SubscriptionStatus.active || s.periodEnd == null;
        final bool confirmed = await confirmAction(
          context,
          title: s.status == SubscriptionStatus.trialing
              ? l10n.subscriptionEndTrial
              : l10n.subscriptionCancelTitle,
          message: now
              ? l10n.subscriptionCancelNowMessage
              : l10n.subscriptionCancelMessage(
                  billingDate(context, s.periodEnd!),
                ),
          confirmLabel: l10n.plansChoose(plan.name.of(language)),
          destructive: true,
        );
        if (confirmed) {
          await _change(
            plan.code,
            () => ref
                .read(subscriptionActionsProvider)
                .downgrade(widget.businessId, plan: plan.code),
            l10n.subscriptionCanceled,
          );
        }
      case _PlanAction.current || _PlanAction.none || _PlanAction.unavailable:
        return;
    }
  }

  Future<void> _change(
    String code,
    Future<void> Function() action,
    String done,
  ) async {
    setState(() => _busyPlan = code);
    try {
      await action();
      if (mounted) {
        showResultMessage(context, done);
        context.go(AppRoutes.subscription(widget.businessId));
      }
    } on AppFailure catch (failure) {
      if (mounted) {
        showResultMessage(context, failureMessage(context.l10n, failure));
      }
    } finally {
      if (mounted) {
        setState(() => _busyPlan = null);
      }
    }
  }

  /// Online payment: the provider's page opens in the browser; the result
  /// comes from the server once the payer is back.
  Future<void> _pay(SubscriptionPlan plan, BillingPeriod period) async {
    final AppLocalizations l10n = context.l10n;
    setState(() => _busyPlan = plan.code);
    CheckoutStart? checkout;
    try {
      checkout = await ref
          .read(subscriptionActionsProvider)
          .startCheckout(widget.businessId, plan: plan.code, period: period);
    } on AppFailure catch (failure) {
      if (mounted) {
        showResultMessage(context, failureMessage(l10n, failure));
      }
    } finally {
      if (mounted) {
        setState(() => _busyPlan = null);
      }
    }
    if (checkout == null || !mounted) {
      return;
    }
    final bool opened = await ref.read(paymentPageLauncherProvider)(
      checkout.paymentUrl,
    );
    if (!mounted) {
      return;
    }
    if (!opened) {
      showResultMessage(context, l10n.paymentCouldNotOpen);
      return;
    }
    final bool? paid = await showModalBottomSheet<bool>(
      context: context,
      isDismissible: true,
      builder: (_) => _PaymentWaitSheet(
        businessId: widget.businessId,
        checkoutId: checkout!.checkoutId,
      ),
    );
    if (paid == true && mounted) {
      showResultMessage(context, l10n.paymentPaid);
      context.go(AppRoutes.subscription(widget.businessId));
    }
  }
}

enum _PlanAction {
  none,
  current,
  choose,
  renew,
  switchLater,
  switchToFree,
  unavailable,
}

class _PlanCard extends StatelessWidget {
  const _PlanCard({
    required this.plan,
    required this.period,
    required this.currency,
    required this.current,
    required this.action,
    required this.busy,
    required this.onAction,
  });

  final SubscriptionPlan plan;
  final BillingPeriod period;
  final String currency;
  final BusinessSubscription current;
  final _PlanAction action;
  final bool busy;
  final ValueChanged<_PlanAction> onAction;

  @override
  Widget build(BuildContext context) {
    final AppLocalizations l10n = context.l10n;
    final TextTheme text = Theme.of(context).textTheme;
    final String language = languageOf(context);
    final String name = plan.name.of(language);
    final PlanPrice? price = plan.priceFor(period, currency);
    final bool isCurrent = current.plan.code == plan.code;
    final String priceText = plan.isFree
        ? l10n.plansFreePrice
        : price == null
        ? l10n.plansNotForPeriod
        : period == BillingPeriod.yearly
        ? l10n.plansPerYear(money(price.amount, price.currency))
        : l10n.plansPerMonth(money(price.amount, price.currency));
    String limit(int? value) => value == null ? l10n.plansUnlimited : '$value';

    final String? label = switch (action) {
      _PlanAction.choose => l10n.plansChoose(name),
      _PlanAction.renew => l10n.plansRenew,
      _PlanAction.switchLater => l10n.plansSwitchLater(name),
      _PlanAction.switchToFree => l10n.plansChoose(name),
      _PlanAction.none ||
      _PlanAction.current ||
      _PlanAction.unavailable => null,
    };

    return DecoratedBox(
      decoration: BoxDecoration(
        borderRadius: BorderRadius.circular(16),
        border: Border.all(
          color: isCurrent ? AppColors.primary : AppColors.border,
          width: isCurrent ? 2 : 1,
        ),
      ),
      child: DoryCard(
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: <Widget>[
            Row(
              children: <Widget>[
                Expanded(child: Text(name, style: text.titleLarge)),
                if (isCurrent) PlanBadge(label: l10n.plansCurrentBadge),
              ],
            ),
            const SizedBox(height: AppSpacing.xs),
            Text(
              priceText,
              style: text.titleMedium?.copyWith(color: AppColors.primary),
            ),
            if (plan.description != null) ...<Widget>[
              const SizedBox(height: AppSpacing.sm),
              Text(plan.description!.of(language), style: text.bodyMedium),
            ],
            const SizedBox(height: AppSpacing.md),
            PlanFeatureRow(
              label:
                  '${l10n.planLimitBranchesLabel}: ${limit(plan.limits.branches)}',
              included: true,
            ),
            PlanFeatureRow(
              label:
                  '${l10n.planLimitQueuesLabel}: ${limit(plan.limits.queues)}',
              included: true,
            ),
            PlanFeatureRow(
              label:
                  '${l10n.planLimitMembersLabel}: ${limit(plan.limits.members)}',
              included: true,
            ),
            PlanFeatureRow(
              label: l10n.planFeatureInsights,
              included: plan.has(PlanFeatures.insights),
            ),
            PlanFeatureRow(
              label: l10n.planFeatureBranchInsights,
              included: plan.has(PlanFeatures.branchInsights),
            ),
            if (label != null) ...<Widget>[
              const SizedBox(height: AppSpacing.md),
              DoryButton(
                label: label,
                variant:
                    action == _PlanAction.choose || action == _PlanAction.renew
                    ? DoryButtonVariant.primary
                    : DoryButtonVariant.secondary,
                isLoading: busy,
                onPressed: () => onAction(action),
              ),
            ],
          ],
        ),
      ),
    );
  }
}

class _ComparisonTable extends StatelessWidget {
  const _ComparisonTable({required this.plans});

  final List<SubscriptionPlan> plans;

  @override
  Widget build(BuildContext context) {
    final AppLocalizations l10n = context.l10n;
    final TextTheme text = Theme.of(context).textTheme;
    final String language = languageOf(context);
    String limit(int? value) => value == null ? l10n.plansUnlimited : '$value';
    Widget cell(String value, {bool header = false}) => Padding(
      padding: const EdgeInsets.symmetric(
        vertical: AppSpacing.sm,
        horizontal: AppSpacing.xs,
      ),
      child: Text(
        value,
        style: header ? text.labelLarge : text.bodySmall,
        textAlign: TextAlign.center,
      ),
    );
    Widget check(bool included) => Padding(
      padding: const EdgeInsets.symmetric(vertical: AppSpacing.sm),
      child: Semantics(
        label: included ? l10n.plansIncluded : l10n.plansNotIncluded,
        child: Icon(
          included ? Icons.check_rounded : Icons.remove_rounded,
          size: 18,
          color: included ? AppColors.success : AppColors.textMuted,
        ),
      ),
    );
    TableRow row(String label, List<Widget> values) => TableRow(
      children: <Widget>[
        Padding(
          padding: const EdgeInsets.symmetric(vertical: AppSpacing.sm),
          child: Text(label, style: text.bodySmall),
        ),
        ...values,
      ],
    );

    return DoryCard(
      padding: const EdgeInsets.all(AppSpacing.md),
      child: Table(
        columnWidths: const <int, TableColumnWidth>{0: FlexColumnWidth(1.6)},
        defaultVerticalAlignment: TableCellVerticalAlignment.middle,
        border: const TableBorder(
          horizontalInside: BorderSide(color: AppColors.border),
        ),
        children: <TableRow>[
          TableRow(
            children: <Widget>[
              const SizedBox.shrink(),
              for (final SubscriptionPlan plan in plans)
                cell(plan.name.of(language), header: true),
            ],
          ),
          row(l10n.planLimitBranchesLabel, <Widget>[
            for (final SubscriptionPlan plan in plans)
              cell(limit(plan.limits.branches)),
          ]),
          row(l10n.planLimitQueuesLabel, <Widget>[
            for (final SubscriptionPlan plan in plans)
              cell(limit(plan.limits.queues)),
          ]),
          row(l10n.planLimitMembersLabel, <Widget>[
            for (final SubscriptionPlan plan in plans)
              cell(limit(plan.limits.members)),
          ]),
          row(l10n.planFeatureEssentials, <Widget>[
            for (final SubscriptionPlan _ in plans) check(true),
          ]),
          row(l10n.planFeatureInsights, <Widget>[
            for (final SubscriptionPlan plan in plans)
              check(plan.has(PlanFeatures.insights)),
          ]),
          row(l10n.planFeatureBranchInsights, <Widget>[
            for (final SubscriptionPlan plan in plans)
              check(plan.has(PlanFeatures.branchInsights)),
          ]),
        ],
      ),
    );
  }
}

/// Waits for the payer to come back from the payment page, then asks the
/// server what happened. Only a payment the server confirmed counts.
class _PaymentWaitSheet extends ConsumerStatefulWidget {
  const _PaymentWaitSheet({required this.businessId, required this.checkoutId});

  final String businessId;
  final String checkoutId;

  @override
  ConsumerState<_PaymentWaitSheet> createState() => _PaymentWaitSheetState();
}

class _PaymentWaitSheetState extends ConsumerState<_PaymentWaitSheet> {
  late final AppLifecycleListener _lifecycle;
  bool _checking = false;
  String? _message;

  @override
  void initState() {
    super.initState();
    // Back from the browser: ask the server at once.
    _lifecycle = AppLifecycleListener(onResume: () => unawaited(_check()));
  }

  @override
  void dispose() {
    _lifecycle.dispose();
    super.dispose();
  }

  Future<void> _check() async {
    if (_checking) {
      return;
    }
    final AppLocalizations l10n = context.l10n;
    setState(() => _checking = true);
    try {
      final CheckoutStatus status = await ref
          .read(subscriptionActionsProvider)
          .checkoutStatus(widget.businessId, widget.checkoutId);
      if (!mounted) {
        return;
      }
      if (status == CheckoutStatus.paid) {
        Navigator.of(context).pop(true);
        return;
      }
      setState(() {
        _message = switch (status) {
          CheckoutStatus.failed => l10n.paymentFailed,
          CheckoutStatus.expired => l10n.paymentExpired,
          CheckoutStatus.pending || CheckoutStatus.paid => l10n.paymentPending,
        };
      });
    } on AppFailure catch (failure) {
      if (mounted) {
        setState(() => _message = failureMessage(l10n, failure));
      }
    } finally {
      if (mounted) {
        setState(() => _checking = false);
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    final AppLocalizations l10n = context.l10n;
    final TextTheme text = Theme.of(context).textTheme;
    return SafeArea(
      child: Padding(
        padding: const EdgeInsets.all(AppSpacing.xl),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: <Widget>[
            const Icon(
              Icons.lock_clock_outlined,
              size: 40,
              color: AppColors.primary,
            ),
            const SizedBox(height: AppSpacing.md),
            Text(
              l10n.paymentWaitingTitle,
              style: text.titleLarge,
              textAlign: TextAlign.center,
            ),
            const SizedBox(height: AppSpacing.sm),
            Text(
              l10n.paymentWaitingMessage,
              style: text.bodyMedium,
              textAlign: TextAlign.center,
            ),
            if (_message != null) ...<Widget>[
              const SizedBox(height: AppSpacing.md),
              DoryNotice(message: _message!),
            ],
            const SizedBox(height: AppSpacing.lg),
            DoryButton(
              label: l10n.paymentCheck,
              icon: Icons.refresh_rounded,
              isLoading: _checking,
              onPressed: () => unawaited(_check()),
            ),
          ],
        ),
      ),
    );
  }
}
