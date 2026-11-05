import 'package:dory_app/app/theme/theme.dart';
import 'package:dory_app/core/widgets/widgets.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:material_ui/material_ui.dart';

import '../../helpers/test_app.dart';

void main() {
  group('DoryEmptyState', () {
    testWidgets('shows icon, title, and message without an action', (
      tester,
    ) async {
      await pumpLocalizedWidget(
        tester,
        const DoryEmptyState(
          icon: Icons.inbox_outlined,
          title: 'Nothing here',
          message: 'Come back later',
        ),
      );

      expect(find.byIcon(Icons.inbox_outlined), findsOneWidget);
      expect(find.text('Nothing here'), findsOneWidget);
      expect(find.text('Come back later'), findsOneWidget);
      expect(find.byType(DoryButton), findsNothing);
    });

    testWidgets('runs its action', (tester) async {
      int taps = 0;
      await pumpLocalizedWidget(
        tester,
        DoryEmptyState(
          icon: Icons.inbox_outlined,
          title: 'Nothing here',
          action: DoryStatusAction(label: 'Refresh', onPressed: () => taps++),
        ),
      );

      await tester.tap(find.text('Refresh'));
      expect(taps, 1);
    });
  });

  group('DoryErrorState', () {
    testWidgets('offers a retry and uses error colors', (tester) async {
      int retries = 0;
      await pumpLocalizedWidget(
        tester,
        DoryErrorState(
          title: 'Something went wrong',
          message: 'Check your connection',
          retry: DoryStatusAction(
            label: 'Try again',
            onPressed: () => retries++,
          ),
        ),
      );

      expect(find.text('Something went wrong'), findsOneWidget);
      final Icon icon = tester.widget(find.byIcon(Icons.error_outline_rounded));
      expect(icon.color, AppTheme.lightColorScheme.error);

      await tester.tap(find.text('Try again'));
      expect(retries, 1);
    });

    testWidgets('omits the retry button when retrying cannot help', (
      tester,
    ) async {
      await pumpLocalizedWidget(
        tester,
        const DoryErrorState(title: 'Not allowed'),
      );
      expect(find.byType(DoryButton), findsNothing);
    });
  });

  group('DoryLoadingIndicator', () {
    testWidgets('shows a spinner and an optional message', (tester) async {
      await pumpLocalizedWidget(
        tester,
        const DoryLoadingIndicator(message: 'Loading queues'),
        settle: false,
      );

      expect(find.byType(CircularProgressIndicator), findsOneWidget);
      expect(find.text('Loading queues'), findsOneWidget);
    });
  });

  group('DoryMetricTile', () {
    testWidgets('shows a dash and announces "no data" without a value', (
      tester,
    ) async {
      final SemanticsHandle semantics = tester.ensureSemantics();
      await pumpLocalizedWidget(
        tester,
        const DoryMetricTile(
          label: 'Waiting',
          icon: Icons.people_outline,
          noValueLabel: 'No data',
        ),
      );

      expect(find.text('—'), findsOneWidget);
      expect(find.text('0'), findsNothing);
      expect(find.bySemanticsLabel(RegExp('No data')), findsOneWidget);
      semantics.dispose();
    });

    testWidgets('shows the value when present', (tester) async {
      await pumpLocalizedWidget(
        tester,
        const DoryMetricTile(
          label: 'Waiting',
          icon: Icons.people_outline,
          noValueLabel: 'No data',
          value: '12',
        ),
      );
      expect(find.text('12'), findsOneWidget);
    });
  });

  group('DoryCard', () {
    testWidgets('is tappable when given onTap', (tester) async {
      int taps = 0;
      await pumpLocalizedWidget(
        tester,
        DoryCard(onTap: () => taps++, child: const Text('Branch')),
      );

      await tester.tap(find.text('Branch'));
      expect(taps, 1);
      expect(find.byType(InkWell), findsOneWidget);
    });

    testWidgets('has no ink response without onTap', (tester) async {
      await pumpLocalizedWidget(tester, const DoryCard(child: Text('Info')));
      expect(find.byType(InkWell), findsNothing);
    });
  });

  group('DoryPageScaffold', () {
    testWidgets('shows its title in the app bar', (tester) async {
      await pumpLocalizedWidget(
        tester,
        const DoryPageScaffold(title: 'Queues', body: Text('Body')),
      );

      expect(
        find.descendant(of: find.byType(AppBar), matching: find.text('Queues')),
        findsOneWidget,
      );
    });

    testWidgets('caps content width on wide screens', (tester) async {
      setTestWindowSize(tester, const Size(1400, 900));
      const Key bodyKey = Key('body');
      await pumpLocalizedWidget(
        tester,
        const DoryPageScaffold(
          body: SizedBox(key: bodyKey, width: double.infinity, height: 10),
        ),
      );

      expect(
        tester.getSize(find.byKey(bodyKey)).width,
        AppLayout.maxContentWidth,
      );
    });
  });

  group('DoryNotice', () {
    testWidgets('shows its message', (tester) async {
      await pumpLocalizedWidget(
        tester,
        const DoryNotice(message: 'Sign-in is not available yet'),
      );
      expect(find.text('Sign-in is not available yet'), findsOneWidget);
      expect(find.byIcon(Icons.info_outline_rounded), findsOneWidget);
    });
  });

  group('DoryLogo', () {
    testWidgets('renders the wordmark and announces brand and tagline', (
      tester,
    ) async {
      final SemanticsHandle semantics = tester.ensureSemantics();
      await pumpLocalizedWidget(
        tester,
        const DoryLogo(tagline: 'Your Turn. Your Time.'),
      );

      expect(find.text('DORY'), findsOneWidget);
      expect(
        find.bySemanticsLabel('DORY. Your Turn. Your Time.'),
        findsOneWidget,
      );
      semantics.dispose();
    });
  });
}
