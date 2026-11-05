import 'package:dory_app/core/network/network_providers.dart';
import 'package:dory_app/features/branch_management/data/api_branch_repository.dart';
import 'package:dory_app/features/branch_management/domain/entities/branch.dart';
import 'package:dory_app/features/branch_management/domain/repositories/branch_repository.dart';
import 'package:dory_app/features/businesses/domain/entities/business_draft.dart';
import 'package:dory_app/features/businesses/presentation/providers/business_providers.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

final Provider<BranchRepository> branchRepositoryProvider =
    Provider<BranchRepository>(
      (ref) => ApiBranchRepository(ref.watch(apiClientProvider)),
      name: 'branchRepositoryProvider',
    );

/// Branches of a business that the user may reach.
final branchesProvider = FutureProvider.autoDispose
    .family<List<Branch>, String>((ref, businessId) {
      ref.watch(currentUserIdProvider);
      return ref.watch(branchRepositoryProvider).branches(businessId);
    }, name: 'branchesProvider');

final branchProvider = FutureProvider.autoDispose.family<Branch, String>((
  ref,
  branchId,
) {
  ref.watch(currentUserIdProvider);
  return ref.watch(branchRepositoryProvider).branch(branchId);
}, name: 'branchProvider');

/// Branch mutations. Each refreshes the lists and counts it affects.
class BranchActions {
  const BranchActions(this._ref);

  final Ref _ref;

  BranchRepository get _repository => _ref.read(branchRepositoryProvider);

  Future<Branch> create(String businessId, BranchDraft draft) async {
    final Branch branch = await _repository.create(businessId, draft);
    _refresh(businessId);
    return branch;
  }

  Future<Branch> update(
    String businessId,
    String branchId,
    BranchDraft draft,
  ) async {
    final Branch branch = await _repository.update(branchId, draft);
    _ref.invalidate(branchProvider(branchId));
    _refresh(businessId);
    return branch;
  }

  Future<void> delete(String businessId, String branchId) async {
    await _repository.delete(branchId);
    _refresh(businessId);
  }

  void _refresh(String businessId) {
    _ref
      ..invalidate(branchesProvider(businessId))
      ..invalidate(businessProvider(businessId))
      ..invalidate(myBusinessesProvider);
  }
}

final Provider<BranchActions> branchActionsProvider = Provider<BranchActions>(
  BranchActions.new,
  name: 'branchActionsProvider',
);
