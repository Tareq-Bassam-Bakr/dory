import 'package:dory_app/core/errors/app_failure.dart';
import 'package:dory_app/core/network/api_client.dart';
import 'package:dory_app/core/network/json.dart';
import 'package:dory_app/features/subscriptions/domain/subscription.dart';

/// The billing endpoints (docs/SUBSCRIPTIONS.md, "API").
class ApiSubscriptionRepository implements SubscriptionRepository {
  const ApiSubscriptionRepository(this._api);

  final ApiClient _api;

  String _base(String businessId) => '/businesses/$businessId/subscription';

  @override
  Future<PlanCatalog> plans() => AppFailure.guard(
    () async => catalogFromJson((await _api.get('/billing/plans')).dataObject),
  );

  @override
  Future<BusinessSubscription> subscription(String businessId) =>
      AppFailure.guard(
        () async => subscriptionFromJson(
          (await _api.get(_base(businessId))).dataObject,
        ),
      );

  @override
  Future<List<BillingInvoice>> invoices(String businessId) => AppFailure.guard(
    () async => (await _api.get(
      '/businesses/$businessId/invoices',
      query: <String, Object?>{'per_page': 50},
    )).dataList.map(invoiceFromJson).toList(),
  );

  @override
  Future<CheckoutStart> startCheckout(
    String businessId, {
    required String plan,
    required BillingPeriod period,
  }) => AppFailure.guard(() async {
    final JsonMap data = (await _api.post(
      '${_base(businessId)}/checkout',
      body: <String, Object?>{'plan': plan, 'billing_period': period.api},
    )).dataObject;
    final JsonMap checkout = data.object('checkout');
    return CheckoutStart(
      checkoutId: checkout.string('id'),
      paymentUrl: Uri.parse(data.string('payment_url')),
      status: CheckoutStatus.fromApi(checkout.stringOrNull('status')),
    );
  });

  @override
  Future<(CheckoutStatus, BusinessSubscription)> checkoutStatus(
    String businessId,
    String checkoutId,
  ) => AppFailure.guard(() async {
    final JsonMap data = (await _api.get(
      '${_base(businessId)}/checkouts/$checkoutId',
    )).dataObject;
    return (
      CheckoutStatus.fromApi(data.object('checkout').stringOrNull('status')),
      subscriptionFromJson(data.object('subscription')),
    );
  });

  @override
  Future<BusinessSubscription> downgrade(
    String businessId, {
    required String plan,
    BillingPeriod? period,
  }) => AppFailure.guard(
    () async => subscriptionFromJson(
      (await _api.post(
        '${_base(businessId)}/downgrade',
        body: <String, Object?>{'plan': plan, 'billing_period': ?period?.api},
      )).dataObject,
    ),
  );

  @override
  Future<BusinessSubscription> cancel(String businessId) => AppFailure.guard(
    () async => subscriptionFromJson(
      (await _api.post('${_base(businessId)}/cancel')).dataObject,
    ),
  );

  @override
  Future<BusinessSubscription> resume(String businessId) => AppFailure.guard(
    () async => subscriptionFromJson(
      (await _api.post('${_base(businessId)}/resume')).dataObject,
    ),
  );

  // ----- JSON -----------------------------------------------------------------

  static LocalizedText _text(JsonMap? json, String fallback) => LocalizedText(
    en: json?.stringOrNull('en') ?? fallback,
    ar: json?.stringOrNull('ar') ?? fallback,
  );

  static PlanLimits _limits(JsonMap? json) => PlanLimits(
    branches: json?.integerOrNull('branches'),
    queues: json?.integerOrNull('queues'),
    members: json?.integerOrNull('members'),
  );

  static Set<String> _features(Object? value) => <String>{
    if (value is List)
      for (final Object? item in value)
        if (item is String) item,
  };

  static PlanPrice? _price(JsonMap json) {
    final BillingPeriod? period = BillingPeriod.fromApi(
      json.stringOrNull('billing_period'),
    );
    if (period == null) {
      return null;
    }
    return PlanPrice(
      period: period,
      currency: json.string('currency'),
      amountMinor: json.integerOrNull('amount_minor') ?? 0,
      amount: json.stringOrNull('amount') ?? '',
    );
  }

  static SubscriptionPlan planFromJson(JsonMap json) {
    final String code = json.string('code');
    final JsonMap? descriptions = json.objectOrNull('descriptions');
    return SubscriptionPlan(
      code: code,
      name: _text(
        json.objectOrNull('names'),
        json.stringOrNull('name') ?? code,
      ),
      description: descriptions == null
          ? null
          : _text(descriptions, json.stringOrNull('description') ?? ''),
      isFree: json.boolean('is_free'),
      limits: _limits(json.objectOrNull('limits')),
      features: _features(json['features']),
      prices: <PlanPrice>[
        for (final JsonMap price in json.objectList('prices')) ?_price(price),
      ],
      sortOrder: json.integerOrNull('sort_order') ?? 0,
    );
  }

  static PlanCatalog catalogFromJson(JsonMap json) {
    final JsonMap? trial = json.objectOrNull('trial');
    return PlanCatalog(
      plans: json.objectList('plans').map(planFromJson).toList()
        ..sort((a, b) => a.sortOrder.compareTo(b.sortOrder)),
      billingEnabled: json.boolean('billing_enabled', fallback: true),
      defaultCurrency: json.stringOrNull('default_currency') ?? 'USD',
      trialPlan: trial?.stringOrNull('plan'),
      trialDays: trial?.integerOrNull('days') ?? 0,
      graceDays: json.integerOrNull('grace_days') ?? 0,
      onlinePayments: json.objectOrNull('payments')?.boolean('online') ?? false,
    );
  }

  static BusinessSubscription subscriptionFromJson(JsonMap json) {
    final JsonMap subscribed = json.object('subscribed_plan');
    final JsonMap? scheduled = json.objectOrNull('scheduled_plan');
    final JsonMap? usage = json.objectOrNull('usage');
    final JsonMap? permissions = json.objectOrNull('permissions');
    final String subscribedCode = subscribed.string('code');
    return BusinessSubscription(
      status: SubscriptionStatus.fromApi(json.stringOrNull('status')),
      billingEnabled: json.boolean('billing_enabled', fallback: true),
      plan: planFromJson(json.object('plan')),
      subscribedPlanCode: subscribedCode,
      subscribedPlanName: _text(
        subscribed.objectOrNull('names'),
        subscribed.stringOrNull('name') ?? subscribedCode,
      ),
      subscribedPlanIsFree: subscribed.boolean('is_free', fallback: true),
      isPaid: json.boolean('is_paid'),
      isComplimentary: json.boolean('is_complimentary'),
      trialEndsAt: json.dateTimeOrNull('trial_ends_at'),
      periodEnd: json.dateTimeOrNull('current_period_end'),
      graceEndsAt: json.dateTimeOrNull('grace_ends_at'),
      cancelAtPeriodEnd: json.boolean('cancel_at_period_end'),
      scheduledPlanCode: scheduled?.stringOrNull('code'),
      scheduledPlanName: scheduled == null
          ? null
          : _text(
              scheduled.objectOrNull('names'),
              scheduled.stringOrNull('name') ?? '',
            ),
      billingPeriod: BillingPeriod.fromApi(json.stringOrNull('billing_period')),
      currency: json.stringOrNull('currency'),
      amount: json.stringOrNull('amount'),
      limits: _limits(json.objectOrNull('limits')),
      usage: PlanUsage(
        branches: usage?.integerOrNull('branches') ?? 0,
        queues: usage?.integerOrNull('queues') ?? 0,
        members: usage?.integerOrNull('members') ?? 0,
      ),
      features: _features(json['features']),
      onlinePayments: json.objectOrNull('payments')?.boolean('online') ?? false,
      canManageBilling: permissions?.boolean('manage_billing') ?? false,
      canViewBilling: permissions?.boolean('view_billing') ?? false,
    );
  }

  static BillingInvoice invoiceFromJson(JsonMap json) {
    final JsonMap? plan = json.objectOrNull('plan');
    return BillingInvoice(
      id: json.string('id'),
      number: json.stringOrNull('number'),
      status: InvoiceStatus.fromApi(json.stringOrNull('status')),
      planName: plan == null
          ? null
          : _text(plan.objectOrNull('names'), plan.stringOrNull('name') ?? ''),
      period: BillingPeriod.fromApi(json.stringOrNull('billing_period')),
      currency: json.stringOrNull('currency') ?? '',
      amount: json.stringOrNull('amount') ?? '',
      periodStart: json.dateTimeOrNull('period_start'),
      periodEnd: json.dateTimeOrNull('period_end'),
      issuedAt: json.dateTimeOrNull('issued_at') ?? DateTime.now(),
      paidAt: json.dateTimeOrNull('paid_at'),
      paymentMethod: json.stringOrNull('payment_method'),
    );
  }
}
