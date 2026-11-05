import 'package:dory_app/core/network/json.dart';
import 'package:flutter/foundation.dart';

/// The success envelope of every API response:
/// `{ success: true, message, data, meta }` (docs/API_CONVENTIONS.md).
@immutable
class ApiEnvelope {
  const ApiEnvelope({required this.data, this.message, this.meta});

  factory ApiEnvelope.fromJson(JsonMap json) {
    return ApiEnvelope(
      data: json['data'],
      message: json.stringOrNull('message'),
      meta: json.objectOrNull('meta'),
    );
  }

  final Object? data;
  final String? message;
  final JsonMap? meta;

  /// `data` as an object; throws [FormatException] otherwise.
  JsonMap get dataObject => asJsonMap(data, context: 'data');

  /// `data` as a list of objects; throws [FormatException] otherwise.
  List<JsonMap> get dataList {
    final Object? value = data;
    if (value is List) {
      return <JsonMap>[
        for (final Object? item in value) asJsonMap(item, context: 'data[]'),
      ];
    }
    throw const FormatException('Expected a list in data.');
  }

  /// Paging details of list endpoints, from `meta.pagination`.
  Pagination? get pagination {
    final JsonMap? paging = meta?.objectOrNull('pagination');
    return paging == null ? null : Pagination.fromJson(paging);
  }
}

/// `meta.pagination` of list endpoints.
@immutable
class Pagination {
  const Pagination({
    required this.currentPage,
    required this.perPage,
    required this.total,
    required this.lastPage,
  });

  factory Pagination.fromJson(JsonMap json) => Pagination(
    currentPage: json.integer('current_page'),
    perPage: json.integer('per_page'),
    total: json.integer('total'),
    lastPage: json.integer('last_page'),
  );

  final int currentPage;
  final int perPage;
  final int total;
  final int lastPage;

  bool get hasMore => currentPage < lastPage;
}

/// One page of a paginated list.
@immutable
class PageResult<T> {
  const PageResult({required this.items, required this.pagination});

  final List<T> items;
  final Pagination pagination;
}
