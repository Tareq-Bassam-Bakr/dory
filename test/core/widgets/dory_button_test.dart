import 'package:dory_app/core/widgets/dory_button.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:material_ui/material_ui.dart';

import '../../helpers/test_app.dart';

void main() {
  group('DoryButton', () {
    testWidgets('shows its label and reports taps', (tester) async {
      int taps = 0;
      await pumpLocalizedWidget(
        tester,
        DoryButton(label: 'Join queue', onPressed: () => taps++),
      );

      await tester.tap(find.text('Join queue'));
      expect(taps, 1);
    });

    testWidgets('is disabled without a callback', (tester) async {
      await pumpLocalizedWidget(
        tester,
        const DoryButton(label: 'Join queue', onPressed: null),
      );

      final FilledButton button = tester.widget(find.byType(FilledButton));
      expect(button.enabled, isFalse);
    });

    testWidgets('while loading: shows a spinner, ignores taps, keeps label', (
      tester,
    ) async {
      final SemanticsHandle semantics = tester.ensureSemantics();
      int taps = 0;
      await pumpLocalizedWidget(
        tester,
        DoryButton(
          label: 'Join queue',
          isLoading: true,
          onPressed: () => taps++,
        ),
        settle: false,
      );

      expect(find.byType(CircularProgressIndicator), findsOneWidget);
      expect(find.bySemanticsLabel('Join queue'), findsOneWidget);
      final FilledButton button = tester.widget(find.byType(FilledButton));
      expect(button.enabled, isFalse);

      await tester.tap(find.byType(FilledButton), warnIfMissed: false);
      expect(taps, 0);
      semantics.dispose();
    });

    testWidgets('maps variants to Material button types', (tester) async {
      await pumpLocalizedWidget(
        tester,
        Column(
          children: <Widget>[
            DoryButton(label: 'A', onPressed: () {}),
            DoryButton(
              label: 'B',
              onPressed: () {},
              variant: DoryButtonVariant.secondary,
            ),
            DoryButton(
              label: 'C',
              onPressed: () {},
              variant: DoryButtonVariant.tertiary,
            ),
          ],
        ),
      );

      expect(find.byType(FilledButton), findsOneWidget);
      expect(find.byType(OutlinedButton), findsOneWidget);
      expect(find.byType(TextButton), findsOneWidget);
    });

    testWidgets('fills the available width unless expand is false', (
      tester,
    ) async {
      setTestWindowSize(tester, const Size(400, 800));
      await pumpLocalizedWidget(
        tester,
        Column(
          children: <Widget>[
            DoryButton(label: 'Wide', onPressed: () {}),
            DoryButton(label: 'Narrow', onPressed: () {}, expand: false),
          ],
        ),
      );

      expect(tester.getSize(find.byType(FilledButton).first).width, 400);
      expect(
        tester.getSize(find.byType(FilledButton).last).width,
        lessThan(400),
      );
    });

    testWidgets('renders a leading icon when given', (tester) async {
      await pumpLocalizedWidget(
        tester,
        DoryButton(label: 'Add', icon: Icons.add, onPressed: () {}),
      );
      expect(find.byIcon(Icons.add), findsOneWidget);
    });
  });
}
