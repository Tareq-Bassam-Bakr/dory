import 'package:dory_app/features/queues/data/api_queue_repository.dart';
import 'package:dory_app/features/queues/domain/entities/queue_entities.dart';
import 'package:flutter_test/flutter_test.dart';

import 'queue_fixtures.dart';

void main() {
  test('a queue configuration is read with its session and permissions', () {
    final QueueConfig queue = ApiQueueRepository.queueFromJson(
      queueJson(session: queueSessionJson(status: 'paused'), manage: false),
    );

    expect(queue.businessId, isNotEmpty);
    expect(queue.ticketPrefix, 'MED');
    expect(queue.isActive, isTrue);
    expect(queue.effectiveServiceMinutes, 20);
    expect(queue.currentSession?.status, QueueSessionStatus.paused);
    expect(queue.canManage, isFalse);
    expect(queue.canOperate, isTrue);
  });

  test('staff tickets keep the API status names, including no_show', () {
    final StaffTicket ticket = ApiQueueRepository.ticketFromJson(
      staffTicketJson(
        status: 'no_show',
        position: null,
        guestName: 'Omar',
        servedBy: 'Lina',
      ),
    );

    expect(ticket.status, TicketStatus.noShow);
    expect(ticket.isGuest, isTrue);
    expect(ticket.customerName, 'Omar');
    expect(ticket.servedByName, 'Lina');
    expect(ticket.position, isNull);
    expect(TicketStatus.noShow.apiValue, 'no_show');
    expect(TicketAction.noShow.path, 'no-show');
  });

  test('the dashboard counts every status', () {
    final QueueDashboard dashboard = ApiQueueRepository.dashboardFromJson(
      dashboardJson(waiting: 4, called: 1, completed: 7),
    );

    expect(dashboard.count(TicketStatus.waiting), 4);
    expect(dashboard.count(TicketStatus.called), 1);
    expect(dashboard.count(TicketStatus.completed), 7);
    expect(dashboard.count(TicketStatus.expired), 0);
    expect(dashboard.nextUp, hasLength(2));
    expect(dashboard.estimatedWaitMinutes, 40);
    expect(dashboard.estimateFromHistory, isFalse);
  });

  test('a draft is sent with upper-case prefix and API field names', () {
    final Map<String, Object?> body = ApiQueueRepository.draftBody(
      const QueueDraft(
        name: ' Haircuts ',
        ticketPrefix: 'hc',
        isActive: false,
        capacity: 30,
        estimatedServiceMinutes: 12,
        gracePeriodSeconds: 60,
      ),
    );

    expect(body, <String, Object?>{
      'name': 'Haircuts',
      'ticket_prefix': 'HC',
      'status': 'inactive',
      'capacity': 30,
      'allow_guest_join': true,
      'require_guest_name': true,
      'require_guest_phone': false,
      'estimated_service_duration': 12,
      'grace_period_seconds': 60,
    });
  });
}
