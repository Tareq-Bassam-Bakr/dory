import 'package:dory_app/core/network/network_providers.dart';
import 'package:dory_app/features/branch_management/presentation/providers/branch_providers.dart';
import 'package:dory_app/features/businesses/domain/entities/business_draft.dart';
import 'package:dory_app/features/businesses/presentation/providers/business_providers.dart';
import 'package:dory_app/features/services/data/api_service_repository.dart';
import 'package:dory_app/features/services/domain/entities/service.dart';
import 'package:dory_app/features/services/domain/repositories/service_repository.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

final Provider<ServiceRepository> serviceRepositoryProvider =
    Provider<ServiceRepository>(
      (ref) => ApiServiceRepository(ref.watch(apiClientProvider)),
      name: 'serviceRepositoryProvider',
    );

final servicesProvider = FutureProvider.autoDispose
    .family<List<Service>, String>((ref, branchId) {
      ref.watch(currentUserIdProvider);
      return ref.watch(serviceRepositoryProvider).services(branchId);
    }, name: 'servicesProvider');

final serviceProvider = FutureProvider.autoDispose.family<Service, String>((
  ref,
  serviceId,
) {
  ref.watch(currentUserIdProvider);
  return ref.watch(serviceRepositoryProvider).service(serviceId);
}, name: 'serviceProvider');

/// Service mutations. Each refreshes the branch's list and counts.
class ServiceActions {
  const ServiceActions(this._ref);

  final Ref _ref;

  ServiceRepository get _repository => _ref.read(serviceRepositoryProvider);

  Future<Service> create(
    String businessId,
    String branchId,
    ServiceDraft draft,
  ) async {
    final Service service = await _repository.create(branchId, draft);
    _refresh(businessId, branchId);
    return service;
  }

  Future<Service> update(
    String businessId,
    String branchId,
    String serviceId,
    ServiceDraft draft,
  ) async {
    final Service service = await _repository.update(serviceId, draft);
    _ref.invalidate(serviceProvider(serviceId));
    _refresh(businessId, branchId);
    return service;
  }

  Future<void> archive(
    String businessId,
    String branchId,
    String serviceId,
  ) async {
    await _repository.archive(serviceId);
    _refresh(businessId, branchId);
  }

  void _refresh(String businessId, String branchId) {
    _ref
      ..invalidate(servicesProvider(branchId))
      ..invalidate(branchProvider(branchId))
      ..invalidate(branchesProvider(businessId))
      ..invalidate(businessProvider(businessId))
      ..invalidate(myBusinessesProvider);
  }
}

final Provider<ServiceActions> serviceActionsProvider =
    Provider<ServiceActions>(
      ServiceActions.new,
      name: 'serviceActionsProvider',
    );
