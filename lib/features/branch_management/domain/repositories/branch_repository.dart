import 'package:dory_app/features/branch_management/domain/entities/branch.dart';
import 'package:dory_app/features/businesses/domain/entities/business_draft.dart';

/// Branches of a business. Every method throws [AppFailure] on failure.
///
/// The API only returns branches the user may reach: a branch manager sees
/// their assigned branches only.
abstract interface class BranchRepository {
  Future<List<Branch>> branches(String businessId);

  Future<Branch> branch(String branchId);

  Future<Branch> create(String businessId, BranchDraft draft);

  Future<Branch> update(String branchId, BranchDraft draft);

  Future<void> delete(String branchId);
}
