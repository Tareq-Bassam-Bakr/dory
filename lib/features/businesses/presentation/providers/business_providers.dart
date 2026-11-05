import 'dart:async';

import 'package:dory_app/core/network/network_providers.dart';
import 'package:dory_app/features/authentication/presentation/providers/auth_controller.dart';
import 'package:dory_app/features/authentication/presentation/providers/auth_state.dart';
import 'package:dory_app/features/businesses/data/repositories/api_business_repository.dart';
import 'package:dory_app/features/businesses/domain/entities/business.dart';
import 'package:dory_app/features/businesses/domain/entities/business_category.dart';
import 'package:dory_app/features/businesses/domain/entities/business_draft.dart';
import 'package:dory_app/features/businesses/domain/repositories/business_repository.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

final Provider<BusinessRepository> businessRepositoryProvider =
    Provider<BusinessRepository>(
      (ref) => ApiBusinessRepository(ref.watch(apiClientProvider)),
      name: 'businessRepositoryProvider',
    );

/// The signed-in user's id, or null. Tenant data providers watch it, so a
/// logout or a different account never sees the previous user's data.
final Provider<String?> currentUserIdProvider = Provider<String?>((ref) {
  final AuthState auth = ref.watch(authControllerProvider);
  return auth is Authenticated ? auth.user.id : null;
}, name: 'currentUserIdProvider');

/// Business categories from the API (public reference data).
final FutureProvider<List<BusinessCategory>> businessCategoriesProvider =
    FutureProvider<List<BusinessCategory>>(
      (ref) => ref.watch(businessRepositoryProvider).categories(),
      name: 'businessCategoriesProvider',
    );

/// Businesses the signed-in user works in.
final FutureProvider<List<Business>> myBusinessesProvider =
    FutureProvider<List<Business>>((ref) async {
      if (ref.watch(currentUserIdProvider) == null) {
        return const <Business>[];
      }
      return ref.watch(businessRepositoryProvider).myBusinesses();
    }, name: 'myBusinessesProvider');

final businessProvider = FutureProvider.autoDispose.family<Business, String>((
  ref,
  businessId,
) {
  ref.watch(currentUserIdProvider);
  return ref.watch(businessRepositoryProvider).business(businessId);
}, name: 'businessProvider');

/// Business mutations. Each refreshes the data it changed.
class BusinessActions {
  const BusinessActions(this._ref);

  final Ref _ref;

  BusinessRepository get _repository => _ref.read(businessRepositoryProvider);

  Future<Business> create(
    BusinessDraft draft, {
    required String idempotencyKey,
  }) async {
    final Business business = await _repository.create(
      draft,
      idempotencyKey: idempotencyKey,
    );
    _afterMembershipChange();
    return business;
  }

  Future<Business> update(
    String businessId,
    BusinessProfileChanges changes,
  ) async {
    final Business business = await _repository.update(businessId, changes);
    _ref
      ..invalidate(businessProvider(businessId))
      ..invalidate(myBusinessesProvider);
    return business;
  }

  Future<void> delete(String businessId) async {
    await _repository.delete(businessId);
    _afterMembershipChange();
  }

  /// The user's memberships changed: reload the list and the profile
  /// (which decides the home workspace).
  void _afterMembershipChange() {
    _ref.invalidate(myBusinessesProvider);
    unawaited(_ref.read(authControllerProvider.notifier).refreshUser());
  }
}

final Provider<BusinessActions> businessActionsProvider =
    Provider<BusinessActions>(
      BusinessActions.new,
      name: 'businessActionsProvider',
    );
