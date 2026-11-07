/// JSON shaped exactly like the Laravel customer resources (see
/// docs/CUSTOMER_API_REFERENCE.md), for fake HTTP responses.
library;

import '../../helpers/api_fixtures.dart';
import '../../helpers/fake_http_adapter.dart';

const String publicBusinessId = '01jpublicbusinessxxxxxxxxx';
const String publicSlug = 'shifa-clinic';
const String publicBranchId = '01jpublicbranchxxxxxxxxxxx';
const String publicServiceId = '01jpublicservicexxxxxxxxxx';
const String publicQueueId = '01jpublicqueuexxxxxxxxxxxx';
const String customerTicketId = '01jcustomerticketxxxxxxxxx';
const String guestAccessToken =
    'GuestTokenGuestTokenGuestTokenGuestTokenGuestTok';

Map<String, Object?> pagination({
  int page = 1,
  int perPage = 15,
  int total = 1,
  int lastPage = 1,
}) => <String, Object?>{
  'pagination': <String, Object?>{
    'current_page': page,
    'per_page': perPage,
    'total': total,
    'last_page': lastPage,
  },
};

FakeResponse paged(
  List<Map<String, Object?>> items, {
  int page = 1,
  int lastPage = 1,
}) => FakeResponse.ok(
  items,
  meta: pagination(page: page, total: items.length, lastPage: lastPage),
);

Map<String, Object?> publicBusinessJson({
  String id = publicBusinessId,
  String slug = publicSlug,
  String name = 'Shifa Clinic',
  int branches = 2,
  int open = 1,
  int accepting = 1,
  int? wait = 15,
  double? distance,
  Map<String, Object?>? rating,
  bool favorite = false,
  bool detailed = false,
}) => <String, Object?>{
  'id': id,
  'slug': slug,
  'name': name,
  'category': categoryJson(),
  'logo_url': null,
  'cover_url': null,
  'branches_count': branches,
  'city': 'Damascus',
  'country': 'SY',
  'distance_km': distance,
  'open_queues_count': open,
  'accepting_queues_count': accepting,
  'lowest_wait_minutes': wait,
  'rating': rating,
  'is_favorite': favorite,
  if (detailed) ...<String, Object?>{
    'description': 'Family medicine for the whole neighbourhood',
    'email': 'hello@shifa.test',
    'phone': '+963933000000',
    'website': 'https://shifa.test',
  },
};

Map<String, Object?> publicBranchJson({
  String id = publicBranchId,
  String name = 'Mazzeh',
  double? latitude = 33.5138,
  double? longitude = 36.2765,
  double? distance,
  int accepting = 1,
  int? wait = 15,
  String businessName = 'Shifa Clinic',
}) => <String, Object?>{
  'id': id,
  'name': name,
  'address': 'Mazzeh Highway 12',
  'city': 'Damascus',
  'region': null,
  'country': 'SY',
  'latitude': latitude,
  'longitude': longitude,
  'phone': '+963933000001',
  'timezone': 'Asia/Damascus',
  'distance_km': distance,
  'services_count': 2,
  'open_queues_count': accepting,
  'accepting_queues_count': accepting,
  'lowest_wait_minutes': wait,
  'business': <String, Object?>{
    'id': publicBusinessId,
    'slug': publicSlug,
    'name': businessName,
    'logo_url': null,
    'category': categoryJson(),
  },
};

Map<String, Object?> queueAvailabilityJson({
  String id = publicQueueId,
  String name = 'Consultations',
  String status = 'open',
  String? reason,
  bool accepting = true,
  bool full = false,
  int waiting = 3,
  int? capacity,
  int? remaining,
  int minutes = 15,
  bool guests = true,
  bool guestName = true,
  bool guestPhone = false,
}) => <String, Object?>{
  'id': id,
  'name': name,
  'status': status,
  'unavailable_reason': reason,
  'accepting_joins': accepting,
  'is_full': full,
  'waiting_count': waiting,
  'capacity': capacity,
  'capacity_remaining': remaining,
  'estimated_wait': <String, Object?>{
    'seconds': minutes * 60,
    'minutes': minutes,
    'basis': 'configured',
  },
  'allow_guest_join': guests,
  'require_guest_name': guestName,
  'require_guest_phone': guestPhone,
};

Map<String, Object?> publicServiceJson({
  String id = publicServiceId,
  String name = 'General consultation',
  Map<String, Object?>? queue,
  bool withQueue = true,
}) => <String, Object?>{
  'id': id,
  'name': name,
  'description': 'See a family doctor',
  'estimated_duration_minutes': 15,
  'queue': withQueue ? (queue ?? queueAvailabilityJson()) : null,
};

Map<String, Object?> availableQueueJson({
  String id = publicQueueId,
  String service = 'General consultation',
  int minutes = 15,
}) => <String, Object?>{
  ...queueAvailabilityJson(id: id, minutes: minutes),
  'service': <String, Object?>{'id': publicServiceId, 'name': service},
  'branch': <String, Object?>{
    'id': publicBranchId,
    'name': 'Mazzeh',
    'city': 'Damascus',
    'distance_km': null,
  },
  'business': <String, Object?>{
    'id': publicBusinessId,
    'slug': publicSlug,
    'name': 'Shifa Clinic',
    'logo_url': null,
    'category': categoryJson(),
  },
};

/// `GET /public/queues/{id}`.
Map<String, Object?> joinableQueueJson({
  String status = 'open',
  String? reason,
  bool accepting = true,
  bool full = false,
  bool guests = true,
  bool guestName = true,
  bool guestPhone = false,
  int? capacity,
  int? remaining,
}) => <String, Object?>{
  ...queueAvailabilityJson(
    status: status,
    reason: reason,
    accepting: accepting,
    full: full,
    guests: guests,
    guestName: guestName,
    guestPhone: guestPhone,
    capacity: capacity,
    remaining: remaining,
  ),
  'business': <String, Object?>{
    'id': publicBusinessId,
    'slug': publicSlug,
    'name': 'Shifa Clinic',
  },
  'branch': <String, Object?>{
    'id': publicBranchId,
    'name': 'Mazzeh',
    'city': 'Damascus',
    'address': 'Mazzeh Highway 12',
  },
  'service': <String, Object?>{'name': 'General consultation'},
  'now_serving': <String>['BAR-002'],
};

Map<String, Object?> customerTicketJson({
  String id = customerTicketId,
  String number = 'BAR-024',
  String status = 'waiting',
  int? position = 5,
  int? ahead = 4,
  int? minutes = 15,
  bool canCancel = true,
  bool canReview = false,
  int? myRating,
  String? arriveBy,
  String? accessToken,
  String sessionStatus = 'open',
}) => <String, Object?>{
  'id': id,
  'ticket_number': number,
  'status': status,
  'business_date': '2026-11-07',
  'queue': <String, Object?>{
    'id': publicQueueId,
    'name': 'Consultations',
    'session_status': sessionStatus,
  },
  'business': <String, Object?>{
    'id': publicBusinessId,
    'slug': publicSlug,
    'name': 'Shifa Clinic',
    'logo_url': null,
  },
  'branch': <String, Object?>{
    'id': publicBranchId,
    'name': 'Mazzeh',
    'city': 'Damascus',
    'address': 'Mazzeh Highway 12',
  },
  'service': <String, Object?>{'name': 'General consultation'},
  'position': position,
  'people_ahead': ahead,
  'estimated_wait': minutes == null
      ? null
      : <String, Object?>{
          'seconds': minutes * 60,
          'minutes': minutes,
          'basis': 'configured',
        },
  'now_serving': <String>['BAR-020'],
  'arrive_by': arriveBy,
  'can_cancel': canCancel,
  'can_review': canReview,
  'my_rating': myRating,
  'cancel_reason': null,
  'joined_at': '2026-11-07T09:00:00+00:00',
  'called_at': null,
  'started_at': null,
  'completed_at': null,
  'ended_at': null,
  'access_token': ?accessToken,
};

/// A fake API answering every customer list with one real-shaped record.
FakeHttpAdapter customerApi() => FakeHttpAdapter()
  ..reply('GET', '/business-categories', FakeResponse.ok(categoriesJson()))
  ..reply(
    'GET',
    '/discovery/businesses',
    paged(<Map<String, Object?>>[publicBusinessJson()]),
  )
  ..reply(
    'GET',
    '/discovery/businesses/$publicSlug',
    FakeResponse.ok(publicBusinessJson(detailed: true)),
  )
  ..reply(
    'GET',
    '/discovery/businesses/$publicSlug/branches',
    paged(<Map<String, Object?>>[publicBranchJson()]),
  )
  ..reply(
    'GET',
    '/discovery/nearby',
    paged(<Map<String, Object?>>[publicBranchJson(distance: 1.2)]),
  )
  ..reply(
    'GET',
    '/discovery/queues',
    paged(<Map<String, Object?>>[availableQueueJson()]),
  )
  ..reply(
    'GET',
    '/discovery/cities',
    paged(<Map<String, Object?>>[
      <String, Object?>{
        'city': 'Damascus',
        'country': 'SY',
        'branches_count': 4,
        'latitude': 33.5138,
        'longitude': 36.2765,
      },
    ]),
  )
  ..reply(
    'GET',
    '/discovery/branches/$publicBranchId',
    FakeResponse.ok(publicBranchJson()),
  )
  ..reply(
    'GET',
    '/discovery/branches/$publicBranchId/services',
    paged(<Map<String, Object?>>[publicServiceJson()]),
  )
  ..reply(
    'GET',
    '/public/queues/$publicQueueId',
    FakeResponse.ok(joinableQueueJson()),
  )
  ..reply(
    'GET',
    '/me/tickets',
    paged(<Map<String, Object?>>[customerTicketJson()]),
  )
  ..reply(
    'GET',
    '/me/favorites',
    paged(<Map<String, Object?>>[publicBusinessJson(favorite: true)]),
  );
