import 'package:dory_app/features/businesses/domain/entities/business_draft.dart';
import 'package:dory_app/features/services/domain/entities/service.dart';

/// Services of a branch. Every method throws [AppFailure] on failure.
abstract interface class ServiceRepository {
  Future<List<Service>> services(String branchId);

  Future<Service> service(String serviceId);

  Future<Service> create(String branchId, ServiceDraft draft);

  Future<Service> update(String serviceId, ServiceDraft draft);

  /// Archives the service; its history is kept on the server.
  Future<void> archive(String serviceId);
}
