import 'package:dory_app/app/router/app_routes.dart';
import 'package:dory_app/features/tickets/data/api_ticket_repository.dart';
import 'package:dory_app/features/tickets/domain/entities/customer_ticket.dart';
import 'package:flutter_test/flutter_test.dart';

import '../../helpers/fake_auth_repository.dart';
import '../../helpers/fake_http_adapter.dart';
import '../../helpers/test_app.dart';
import '../customer/customer_fixtures.dart';

/// The smart wait estimate's likely range (docs/WAIT_TIME_ESTIMATION.md).
void main() {
  Map<String, Object?> withRange() {
    final Map<String, Object?> json = customerTicketJson();
    json['estimated_wait'] = <String, Object?>{
      'seconds': 900,
      'minutes': 15,
      'basis': 'history',
      'confidence': 'medium',
      'range': <String, Object?>{'low_minutes': 12, 'high_minutes': 19},
    };
    return json;
  }

  test('the range is read from the estimate', () {
    final CustomerTicket ticket = ApiTicketRepository.ticketFromJson(
      withRange(),
    );
    expect(ticket.estimatedMinutes, 15);
    expect(ticket.waitRangeLowMinutes, 12);
    expect(ticket.waitRangeHighMinutes, 19);

    final CustomerTicket plain = ApiTicketRepository.ticketFromJson(
      customerTicketJson(),
    );
    expect(plain.waitRangeLowMinutes, isNull);
  });

  testWidgets('the ticket shows the likely range under the estimate', (
    tester,
  ) async {
    final FakeHttpAdapter http = customerApi()
      ..reply(
        'GET',
        '/tickets/$customerTicketId/position',
        FakeResponse.ok(withRange()),
      );

    final router = await pumpAppAt(
      tester,
      AppRoutes.customerTicket(customerTicketId),
      authRepository: FakeAuthRepository.signedIn(),
      http: http,
    );

    expect(find.text('Usually 12 to 19 min'), findsOneWidget);
    // Leave the ticket so its refresh timer ends with the test.
    router.go(AppRoutes.customerHome);
    await tester.pumpAndSettle();
  });
}
