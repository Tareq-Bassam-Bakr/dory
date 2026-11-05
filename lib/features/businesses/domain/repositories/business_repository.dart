import 'package:dory_app/features/businesses/domain/entities/business.dart';
import 'package:dory_app/features/businesses/domain/entities/business_category.dart';
import 'package:dory_app/features/businesses/domain/entities/business_draft.dart';

/// Businesses and categories. Every method throws [AppFailure] on failure.
abstract interface class BusinessRepository {
  /// Active categories in display order (public).
  Future<List<BusinessCategory>> categories();

  /// Businesses where the user is an active member.
  Future<List<Business>> myBusinesses();

  Future<Business> business(String businessId);

  /// Creates the business with its first branch and services in one
  /// request. [idempotencyKey] makes retries return the same business.
  Future<Business> create(
    BusinessDraft draft, {
    required String idempotencyKey,
  });

  Future<Business> update(String businessId, BusinessProfileChanges changes);

  Future<void> delete(String businessId);
}
