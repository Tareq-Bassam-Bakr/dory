import 'package:dory_app/core/network/api_envelope.dart';
import 'package:dory_app/features/discovery/domain/entities/discovery_entities.dart';

/// Businesses the signed-in customer saved, kept on the server
/// (docs/CUSTOMER_FAVORITES.md). Every method throws [AppFailure].
abstract interface class FavoriteRepository {
  Future<PageResult<PublicBusiness>> favorites({int page = 1});

  /// Saving twice is not an error.
  Future<PublicBusiness> add(String businessId);

  Future<void> remove(String businessId);
}
