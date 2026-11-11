import 'package:dory_app/app/localization/l10n.dart';
import 'package:dory_app/app/theme/theme.dart';
import 'package:dory_app/core/widgets/widgets.dart';
import 'package:dory_app/features/subscriptions/domain/subscription.dart';
import 'package:dory_app/features/subscriptions/presentation/providers/subscription_providers.dart';
import 'package:dory_app/features/subscriptions/presentation/widgets/subscription_widgets.dart';
import 'package:dory_app/shared/components/async_value_view.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:material_ui/material_ui.dart';

/// Payments of the business, newest first (owners). Failed attempts are
/// listed too, so an owner sees why a plan did not start.
class BillingHistoryPage extends ConsumerWidget {
  const BillingHistoryPage({super.key, required this.businessId});

  final String businessId;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final AppLocalizations l10n = context.l10n;
    final AsyncValue<List<BillingInvoice>> invoices = ref.watch(
      invoicesProvider(businessId),
    );

    return DoryPageScaffold(
      title: l10n.billingHistoryTitle,
      scrollable: false,
      padding: EdgeInsets.zero,
      body: RefreshIndicator(
        onRefresh: () => ref.refresh(invoicesProvider(businessId).future),
        child: ListView(
          padding: const EdgeInsets.all(AppSpacing.pageGutter),
          children: <Widget>[
            AsyncValueView<List<BillingInvoice>>(
              value: invoices,
              onRetry: () => ref.invalidate(invoicesProvider(businessId)),
              data: (items) => items.isEmpty
                  ? Padding(
                      padding: const EdgeInsets.symmetric(
                        vertical: AppSpacing.xxl,
                      ),
                      child: DoryEmptyState(
                        icon: Icons.receipt_long_outlined,
                        title: l10n.billingHistoryEmpty,
                      ),
                    )
                  : Column(
                      children: <Widget>[
                        for (final BillingInvoice invoice in items)
                          Padding(
                            padding: const EdgeInsets.only(
                              bottom: AppSpacing.md,
                            ),
                            child: _InvoiceCard(invoice: invoice),
                          ),
                      ],
                    ),
            ),
          ],
        ),
      ),
    );
  }
}

class _InvoiceCard extends StatelessWidget {
  const _InvoiceCard({required this.invoice});

  final BillingInvoice invoice;

  @override
  Widget build(BuildContext context) {
    final AppLocalizations l10n = context.l10n;
    final TextTheme text = Theme.of(context).textTheme;
    final String language = languageOf(context);
    final (
      String status,
      Color background,
      Color foreground,
    ) = switch (invoice.status) {
      InvoiceStatus.paid => (
        l10n.billingStatusPaid,
        AppColors.successContainer,
        AppColors.success,
      ),
      InvoiceStatus.failed => (
        l10n.billingStatusFailed,
        AppColors.errorContainer,
        AppColors.onErrorContainer,
      ),
      InvoiceStatus.voided => (
        l10n.billingStatusVoid,
        AppColors.surfaceStrong,
        AppColors.textSecondary,
      ),
      InvoiceStatus.open => (
        l10n.billingStatusOpen,
        AppColors.warningContainer,
        AppColors.warning,
      ),
    };
    final String? method = switch (invoice.paymentMethod) {
      'card' => l10n.billingMethodCard,
      'bank_transfer' => l10n.billingMethodBankTransfer,
      'cash' => l10n.billingMethodCash,
      'mobile_wallet' => l10n.billingMethodMobileWallet,
      null => null,
      _ => l10n.billingMethodOther,
    };
    final String period = switch (invoice.period) {
      BillingPeriod.yearly => l10n.plansYearly,
      BillingPeriod.monthly || null => l10n.plansMonthly,
    };

    return DoryCard(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: <Widget>[
          Row(
            children: <Widget>[
              Expanded(
                child: Text(
                  invoice.planName == null
                      ? period
                      : '${invoice.planName!.of(language)} · $period',
                  style: text.titleMedium,
                ),
              ),
              DecoratedBox(
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
                    status,
                    style: text.labelMedium?.copyWith(
                      color: foreground,
                      fontWeight: FontWeight.w600,
                    ),
                  ),
                ),
              ),
            ],
          ),
          const SizedBox(height: AppSpacing.xs),
          Text(money(invoice.amount, invoice.currency), style: text.titleLarge),
          const SizedBox(height: AppSpacing.xs),
          if (invoice.number != null)
            Text(
              '\u2066${invoice.number!}\u2069 · ${billingDate(context, invoice.paidAt ?? invoice.issuedAt)}',
              style: text.bodySmall,
            ),
          if (invoice.periodStart != null && invoice.periodEnd != null)
            Text(
              l10n.billingPeriodRange(
                billingDate(context, invoice.periodStart!),
                billingDate(context, invoice.periodEnd!),
              ),
              style: text.bodySmall,
            ),
          if (method != null) Text(method, style: text.bodySmall),
        ],
      ),
    );
  }
}
