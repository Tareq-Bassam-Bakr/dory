import 'package:flutter/foundation.dart';

/// Plans and subscriptions of a business (docs/SUBSCRIPTIONS.md). The server
/// decides everything: limits are enforced there, this only describes them.

enum BillingPeriod {
  monthly,
  yearly;

  static BillingPeriod? fromApi(String? value) => switch (value) {
    'monthly' => BillingPeriod.monthly,
    'yearly' => BillingPeriod.yearly,
    _ => null,
  };

  String get api => name;
}

/// The things a plan limits. Null limits are unlimited.
enum PlanLimitKind {
  branches,
  queues,
  members;

  static PlanLimitKind? fromApi(String? value) => switch (value) {
    'branches' => PlanLimitKind.branches,
    'queues' => PlanLimitKind.queues,
    'members' => PlanLimitKind.members,
    _ => null,
  };
}

/// Features a plan may include.
abstract final class PlanFeatures {
  static const String insights = 'insights';
  static const String branchInsights = 'branch_insights';
}

@immutable
class PlanLimits {
  const PlanLimits({this.branches, this.queues, this.members});

  final int? branches;
  final int? queues;
  final int? members;

  int? of(PlanLimitKind kind) => switch (kind) {
    PlanLimitKind.branches => branches,
    PlanLimitKind.queues => queues,
    PlanLimitKind.members => members,
  };
}

@immutable
class PlanUsage {
  const PlanUsage({this.branches = 0, this.queues = 0, this.members = 0});

  final int branches;
  final int queues;
  final int members;

  int of(PlanLimitKind kind) => switch (kind) {
    PlanLimitKind.branches => branches,
    PlanLimitKind.queues => queues,
    PlanLimitKind.members => members,
  };
}

@immutable
class PlanPrice {
  const PlanPrice({
    required this.period,
    required this.currency,
    required this.amountMinor,
    required this.amount,
  });

  final BillingPeriod period;
  final String currency;
  final int amountMinor;

  /// Decimal text from the server, e.g. "15.00".
  final String amount;
}

/// A name or description in English and Arabic.
@immutable
class LocalizedText {
  const LocalizedText({required this.en, required this.ar});

  final String en;
  final String ar;

  String of(String languageCode) =>
      languageCode == 'ar' && ar.isNotEmpty ? ar : en;
}

@immutable
class SubscriptionPlan {
  const SubscriptionPlan({
    required this.code,
    required this.name,
    this.description,
    required this.isFree,
    required this.limits,
    this.features = const <String>{},
    this.prices = const <PlanPrice>[],
    this.sortOrder = 0,
  });

  final String code;
  final LocalizedText name;
  final LocalizedText? description;
  final bool isFree;
  final PlanLimits limits;
  final Set<String> features;
  final List<PlanPrice> prices;
  final int sortOrder;

  bool has(String feature) => features.contains(feature);

  PlanPrice? priceFor(BillingPeriod period, String currency) {
    for (final PlanPrice price in prices) {
      if (price.period == period && price.currency == currency) {
        return price;
      }
    }
    return null;
  }
}

/// GET /billing/plans.
@immutable
class PlanCatalog {
  const PlanCatalog({
    required this.plans,
    required this.billingEnabled,
    required this.defaultCurrency,
    this.trialPlan,
    this.trialDays = 0,
    this.graceDays = 0,
    this.onlinePayments = false,
  });

  final List<SubscriptionPlan> plans;
  final bool billingEnabled;
  final String defaultCurrency;
  final String? trialPlan;
  final int trialDays;
  final int graceDays;
  final bool onlinePayments;

  SubscriptionPlan? plan(String code) {
    for (final SubscriptionPlan plan in plans) {
      if (plan.code == code) {
        return plan;
      }
    }
    return null;
  }
}

enum SubscriptionStatus {
  trialing,
  active,
  pastDue,
  expired;

  static SubscriptionStatus fromApi(String? value) => switch (value) {
    'trialing' => SubscriptionStatus.trialing,
    'past_due' => SubscriptionStatus.pastDue,
    'expired' => SubscriptionStatus.expired,
    _ => SubscriptionStatus.active,
  };
}

/// GET /businesses/{id}/subscription: where the business stands now.
@immutable
class BusinessSubscription {
  const BusinessSubscription({
    required this.status,
    required this.billingEnabled,
    required this.plan,
    required this.subscribedPlanCode,
    required this.subscribedPlanName,
    this.subscribedPlanIsFree = true,
    this.isPaid = false,
    this.isComplimentary = false,
    this.trialEndsAt,
    this.periodEnd,
    this.graceEndsAt,
    this.cancelAtPeriodEnd = false,
    this.scheduledPlanCode,
    this.scheduledPlanName,
    this.billingPeriod,
    this.currency,
    this.amount,
    required this.limits,
    required this.usage,
    this.features = const <String>{},
    this.onlinePayments = false,
    this.canManageBilling = false,
    this.canViewBilling = false,
  });

  final SubscriptionStatus status;
  final bool billingEnabled;

  /// In force now: its limits and features apply.
  final SubscriptionPlan plan;

  /// On record, e.g. "pro" for a Pro plan that has ended.
  final String subscribedPlanCode;
  final LocalizedText subscribedPlanName;
  final bool subscribedPlanIsFree;
  final bool isPaid;
  final bool isComplimentary;
  final DateTime? trialEndsAt;
  final DateTime? periodEnd;
  final DateTime? graceEndsAt;
  final bool cancelAtPeriodEnd;
  final String? scheduledPlanCode;
  final LocalizedText? scheduledPlanName;
  final BillingPeriod? billingPeriod;
  final String? currency;
  final String? amount;
  final PlanLimits limits;
  final PlanUsage usage;
  final Set<String> features;
  final bool onlinePayments;
  final bool canManageBilling;
  final bool canViewBilling;

  bool isOverLimit(PlanLimitKind kind) {
    final int? max = limits.of(kind);
    return max != null && usage.of(kind) > max;
  }
}

enum InvoiceStatus {
  open,
  paid,
  failed,
  voided;

  static InvoiceStatus fromApi(String? value) => switch (value) {
    'paid' => InvoiceStatus.paid,
    'failed' => InvoiceStatus.failed,
    'void' => InvoiceStatus.voided,
    _ => InvoiceStatus.open,
  };
}

@immutable
class BillingInvoice {
  const BillingInvoice({
    required this.id,
    this.number,
    required this.status,
    this.planName,
    this.period,
    required this.currency,
    required this.amount,
    this.periodStart,
    this.periodEnd,
    required this.issuedAt,
    this.paidAt,
    this.paymentMethod,
  });

  final String id;
  final String? number;
  final InvoiceStatus status;
  final LocalizedText? planName;
  final BillingPeriod? period;
  final String currency;
  final String amount;
  final DateTime? periodStart;
  final DateTime? periodEnd;
  final DateTime issuedAt;
  final DateTime? paidAt;
  final String? paymentMethod;
}

enum CheckoutStatus {
  pending,
  paid,
  failed,
  expired;

  static CheckoutStatus fromApi(String? value) => switch (value) {
    'paid' => CheckoutStatus.paid,
    'failed' => CheckoutStatus.failed,
    'expired' => CheckoutStatus.expired,
    _ => CheckoutStatus.pending,
  };
}

/// POST /businesses/{id}/subscription/checkout.
@immutable
class CheckoutStart {
  const CheckoutStart({
    required this.checkoutId,
    required this.paymentUrl,
    required this.status,
  });

  final String checkoutId;
  final Uri paymentUrl;
  final CheckoutStatus status;
}

/// Plans and subscriptions. Every method throws AppFailure on failure.
abstract interface class SubscriptionRepository {
  Future<PlanCatalog> plans();

  Future<BusinessSubscription> subscription(String businessId);

  Future<List<BillingInvoice>> invoices(String businessId);

  Future<CheckoutStart> startCheckout(
    String businessId, {
    required String plan,
    required BillingPeriod period,
  });

  Future<(CheckoutStatus, BusinessSubscription)> checkoutStatus(
    String businessId,
    String checkoutId,
  );

  Future<BusinessSubscription> downgrade(
    String businessId, {
    required String plan,
    BillingPeriod? period,
  });

  Future<BusinessSubscription> cancel(String businessId);

  Future<BusinessSubscription> resume(String businessId);
}
