import 'package:dory_app/core/network/network_providers.dart';
import 'package:dory_app/features/businesses/presentation/providers/business_providers.dart';
import 'package:dory_app/features/subscriptions/data/api_subscription_repository.dart';
import 'package:dory_app/features/subscriptions/domain/subscription.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:material_ui/material_ui.dart';

final Provider<SubscriptionRepository> subscriptionRepositoryProvider =
    Provider<SubscriptionRepository>(
      (ref) => ApiSubscriptionRepository(ref.watch(apiClientProvider)),
      name: 'subscriptionRepositoryProvider',
    );

/// The plans a business can choose (public).
final FutureProvider<PlanCatalog> planCatalogProvider =
    FutureProvider.autoDispose<PlanCatalog>(
      (ref) => ref.watch(subscriptionRepositoryProvider).plans(),
      retry: noAutomaticRetry,
      name: 'planCatalogProvider',
    );

/// Where a business's subscription stands (owners and managers).
final subscriptionProvider = FutureProvider.autoDispose
    .family<BusinessSubscription, String>(
      (ref, businessId) {
        ref.watch(currentUserIdProvider);
        return ref
            .watch(subscriptionRepositoryProvider)
            .subscription(businessId);
      },
      retry: noAutomaticRetry,
      name: 'subscriptionProvider',
    );

/// The business overview keeps [subscriptionProvider] loaded while the
/// business is open. Pages about the plan call this when they open, so a
/// change made elsewhere (a payment recorded by DORY, a trial that ended)
/// shows at once. The page keeps showing the loaded values meanwhile.
void refreshSubscriptionOnOpen(WidgetRef ref, String businessId) {
  WidgetsBinding.instance.addPostFrameCallback((_) {
    if (ref.context.mounted) {
      ref.invalidate(subscriptionProvider(businessId));
    }
  });
}

/// Billing history (owners).
final invoicesProvider = FutureProvider.autoDispose
    .family<List<BillingInvoice>, String>(
      (ref, businessId) {
        ref.watch(currentUserIdProvider);
        return ref.watch(subscriptionRepositoryProvider).invoices(businessId);
      },
      retry: noAutomaticRetry,
      name: 'invoicesProvider',
    );

/// Changes of a subscription. Each refreshes what depends on the plan.
final Provider<SubscriptionActions> subscriptionActionsProvider =
    Provider<SubscriptionActions>(
      SubscriptionActions.new,
      name: 'subscriptionActionsProvider',
    );

class SubscriptionActions {
  const SubscriptionActions(this._ref);

  final Ref _ref;

  SubscriptionRepository get _repository =>
      _ref.read(subscriptionRepositoryProvider);

  Future<CheckoutStart> startCheckout(
    String businessId, {
    required String plan,
    required BillingPeriod period,
  }) => _repository.startCheckout(businessId, plan: plan, period: period);

  Future<CheckoutStatus> checkoutStatus(
    String businessId,
    String checkoutId,
  ) async {
    final (CheckoutStatus status, _) = await _repository.checkoutStatus(
      businessId,
      checkoutId,
    );
    if (status == CheckoutStatus.paid) {
      refresh(businessId);
    }
    return status;
  }

  Future<void> downgrade(
    String businessId, {
    required String plan,
    BillingPeriod? period,
  }) async {
    await _repository.downgrade(businessId, plan: plan, period: period);
    refresh(businessId);
  }

  Future<void> cancel(String businessId) async {
    await _repository.cancel(businessId);
    refresh(businessId);
  }

  Future<void> resume(String businessId) async {
    await _repository.resume(businessId);
    refresh(businessId);
  }

  void refresh(String businessId) {
    _ref
      ..invalidate(subscriptionProvider(businessId))
      ..invalidate(invoicesProvider(businessId))
      ..invalidate(businessProvider(businessId));
  }
}
