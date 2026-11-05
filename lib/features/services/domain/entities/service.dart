import 'package:dory_app/features/businesses/domain/entities/business_draft.dart';
import 'package:flutter/foundation.dart';

/// Something a branch offers. One generic model for every sector.
@immutable
class Service {
  const Service({
    required this.id,
    required this.name,
    required this.durationMinutes,
    required this.displayOrder,
    required this.isActive,
    this.branchId,
    this.description,
  });

  final String id;
  final String? branchId;
  final String name;
  final String? description;
  final int durationMinutes;
  final int displayOrder;
  final bool isActive;

  ServiceDraft toDraft() => ServiceDraft(
    name: name,
    description: description ?? '',
    durationMinutes: durationMinutes,
    isActive: isActive,
  );
}
