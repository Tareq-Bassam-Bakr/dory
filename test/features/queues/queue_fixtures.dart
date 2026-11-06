import '../../helpers/api_fixtures.dart';

/// API payloads of the queue engine, shaped like the Laravel resources.
const String queueId = '01jqueuexxxxxxxxxxxxxxxxxx';
const String sessionId = '01jsessionxxxxxxxxxxxxxxxx';
const String ticketId = '01jticketxxxxxxxxxxxxxxxxx';

Map<String, Object?> queueSessionJson({
  String id = sessionId,
  String status = 'open',
  int number = 1,
  int? tickets,
  String? closeReason,
}) => <String, Object?>{
  'id': id,
  'business_date': '2026-11-06',
  'session_number': number,
  'status': status,
  'opened_at': '2026-11-06T06:00:00+00:00',
  'paused_at': status == 'paused' ? '2026-11-06T07:00:00+00:00' : null,
  'closed_at': status == 'closed' ? '2026-11-06T15:00:00+00:00' : null,
  'close_reason': closeReason,
  'tickets_count': ?tickets,
};

Map<String, Object?> queueJson({
  String id = queueId,
  String name = 'Consultations',
  String prefix = 'MED',
  Map<String, Object?>? session,
  bool manage = true,
  bool operate = true,
  bool active = true,
  bool guests = true,
}) => <String, Object?>{
  'id': id,
  'name': name,
  'business': <String, Object?>{'id': businessId, 'name': 'Shifa Clinic'},
  'branch': <String, Object?>{'id': branchId, 'name': 'Mazzeh'},
  'service': <String, Object?>{'id': serviceId, 'name': 'General consultation'},
  'ticket_prefix': prefix,
  'status': active ? 'active' : 'inactive',
  'capacity': null,
  'allow_guest_join': guests,
  'require_guest_name': true,
  'require_guest_phone': false,
  'estimated_service_duration': null,
  'effective_service_minutes': 20,
  'grace_period_seconds': 120,
  'settings': <String, Object?>{},
  'current_session': session,
  'permissions': <String, Object?>{'manage': manage, 'operate': operate},
  'created_at': '2026-11-06T06:00:00+00:00',
  'updated_at': '2026-11-06T06:00:00+00:00',
};

Map<String, Object?> staffTicketJson({
  String id = ticketId,
  String number = 'MED-001',
  String status = 'waiting',
  int? position = 1,
  String? guestName,
  String? phone,
  String? servedBy,
  bool servedByMe = false,
  String? noShowAllowedAt,
  List<Map<String, Object?>> events = const <Map<String, Object?>>[],
}) => <String, Object?>{
  'id': id,
  'ticket_number': number,
  'status': status,
  'priority': 0,
  'join_source': guestName == null ? 'app' : 'public_link',
  'customer': guestName == null
      ? <String, Object?>{'type': 'customer', 'name': 'Rania', 'phone': null}
      : <String, Object?>{'type': 'guest', 'name': guestName, 'phone': phone},
  'position': position,
  'served_by': servedBy == null
      ? null
      : <String, Object?>{
          'id': '01juser',
          'name': servedBy,
          'is_you': servedByMe,
        },
  'recall_count': 0,
  'cancel_reason': null,
  'waited_seconds': 300,
  'service_seconds': null,
  'no_show_allowed_at': noShowAllowedAt,
  'joined_at': '2026-11-06T06:10:00+00:00',
  'called_at': status == 'waiting' ? null : '2026-11-06T06:15:00+00:00',
  'started_at': null,
  'completed_at': null,
  'ended_at': null,
  'events': ?(events.isEmpty ? null : events),
};

Map<String, Object?> dashboardJson({
  int waiting = 2,
  int called = 0,
  int completed = 3,
  List<Map<String, Object?>>? nextUp,
  List<Map<String, Object?>> inService = const <Map<String, Object?>>[],
  Map<String, Object?>? mine,
  bool manage = true,
  bool operate = true,
  bool current = true,
  String status = 'open',
}) => <String, Object?>{
  'session': queueSessionJson(status: status),
  'queue': <String, Object?>{
    'id': queueId,
    'name': 'Consultations',
    'ticket_prefix': 'MED',
    'status': 'active',
    'grace_period_seconds': 120,
  },
  'is_current_session': current,
  'counts': <String, Object?>{
    'waiting': waiting,
    'called': called,
    'serving': 0,
    'completed': completed,
    'cancelled': 0,
    'skipped': 0,
    'no_show': 0,
    'expired': 0,
    'total': waiting + called + completed,
  },
  'next_up':
      nextUp ??
      <Map<String, Object?>>[
        staffTicketJson(id: '01jticketaxxxxxxxxxxxxxxxx', number: 'MED-004'),
        staffTicketJson(
          id: '01jticketbxxxxxxxxxxxxxxxx',
          number: 'MED-005',
          position: 2,
          guestName: 'Omar',
          phone: '+963••••••456',
        ),
      ],
  'in_service': inService,
  'my_ticket': mine,
  'estimated_wait': <String, Object?>{
    'seconds': 2400,
    'minutes': 40,
    'basis': 'configured',
    'seconds_per_customer': 1200,
    'servers': 1,
  },
  'permissions': <String, Object?>{'manage': manage, 'operate': operate},
};
