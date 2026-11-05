import 'package:dory_app/core/widgets/dory_text_field.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:material_ui/material_ui.dart';

import '../../helpers/test_app.dart';

void main() {
  group('DoryTextField', () {
    testWidgets('shows its label and accepts input', (tester) async {
      final TextEditingController controller = TextEditingController();
      addTearDown(controller.dispose);
      await pumpLocalizedWidget(
        tester,
        DoryTextField(label: 'Email', controller: controller),
      );

      expect(find.text('Email'), findsOneWidget);
      await tester.enterText(find.byType(TextFormField), 'a@dory.app');
      expect(controller.text, 'a@dory.app');
    });

    testWidgets('a regular field is not obscured and has no toggle', (
      tester,
    ) async {
      await pumpLocalizedWidget(tester, const DoryTextField(label: 'Name'));

      expect(
        tester.widget<EditableText>(find.byType(EditableText)).obscureText,
        isFalse,
      );
      expect(find.byType(IconButton), findsNothing);
    });

    testWidgets('a password field is obscured and can be revealed', (
      tester,
    ) async {
      await pumpLocalizedWidget(
        tester,
        const DoryTextField(
          label: 'Password',
          passwordVisibilityLabels: PasswordVisibilityLabels(
            show: 'Show password',
            hide: 'Hide password',
          ),
        ),
      );

      bool obscured() =>
          tester.widget<EditableText>(find.byType(EditableText)).obscureText;

      expect(obscured(), isTrue);
      expect(find.byIcon(Icons.visibility_outlined), findsOneWidget);

      await tester.tap(find.byType(IconButton));
      await tester.pump();
      expect(obscured(), isFalse);
      expect(find.byIcon(Icons.visibility_off_outlined), findsOneWidget);

      await tester.tap(find.byType(IconButton));
      await tester.pump();
      expect(obscured(), isTrue);
    });

    testWidgets('a password field disables suggestions and autocorrect', (
      tester,
    ) async {
      await pumpLocalizedWidget(
        tester,
        const DoryTextField(
          label: 'Password',
          passwordVisibilityLabels: PasswordVisibilityLabels(
            show: 'Show',
            hide: 'Hide',
          ),
        ),
      );

      final EditableText editable = tester.widget(find.byType(EditableText));
      expect(editable.autocorrect, isFalse);
      expect(editable.enableSuggestions, isFalse);
    });

    testWidgets('shows validation errors from its validator', (tester) async {
      final GlobalKey<FormState> formKey = GlobalKey<FormState>();
      await pumpLocalizedWidget(
        tester,
        Form(
          key: formKey,
          child: DoryTextField(
            label: 'Email',
            validator: (value) =>
                (value == null || value.isEmpty) ? 'Required' : null,
          ),
        ),
      );

      expect(formKey.currentState!.validate(), isFalse);
      await tester.pump();
      expect(find.text('Required'), findsOneWidget);
    });

    testWidgets('can force left-to-right input inside an RTL layout', (
      tester,
    ) async {
      await pumpLocalizedWidget(
        tester,
        const DoryTextField(label: 'Email', textDirection: TextDirection.ltr),
        locale: const Locale('ar'),
      );

      final EditableText editable = tester.widget(find.byType(EditableText));
      expect(editable.textDirection, TextDirection.ltr);
    });
  });
}
