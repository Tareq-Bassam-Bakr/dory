# Localization

DORY ships in **English (LTR)** and **Arabic (RTL)**. All user-facing text is
translatable. The only exception is the brand name "DORY", which is a
trademark and stays Latin in every locale.

## How it works

- Strings live in ARB files: `lib/app/localization/arb/app_en.arb` (template)
  and `app_ar.arb`.
- `flutter gen-l10n` (run automatically by `flutter pub get`, `flutter run`,
  and `flutter test` because `generate: true` is set) writes
  `lib/app/localization/generated/`. Never edit the generated files.
- Access strings in widgets with `context.l10n.someKey`
  (`import 'package:dory_app/app/localization/l10n.dart';`).
- `AppLocales.delegates` combines `AppLocalizations.delegate` with
  `material_ui`'s `GlobalMaterialLocalizations.delegates` (Material, Cupertino,
  and Widgets). Do **not** use the generated
  `AppLocalizations.localizationsDelegates`: it references the framework's
  legacy Material library, which `material_ui` widgets cannot see.

## Language selection

`AppLanguage` has three options:

| Option | Behaviour |
|---|---|
| `system` (default) | Follows the device language. Unsupported languages fall back to English. Arabic regional variants (`ar-SY`, `ar-SA`, ...) resolve to Arabic. |
| `english` | Forces English. |
| `arabic` | Forces Arabic. |

`LanguageController` (`languageControllerProvider`) holds the choice. It
applies the change immediately and persists it under the storage key
`dory.language_code`. The saved choice is read synchronously before the
first frame, so the app never flashes in the wrong language. Users can change
the language from the welcome screen (translate icon) and from the Account
tab. Language names are always shown in their own language ("English",
"العربية").

## RTL rules

Flutter mirrors layouts automatically from the active locale. To keep that
working:

- Use `EdgeInsetsDirectional`, `AlignmentDirectional`, `start`/`end`, and
  `PositionedDirectional`. Avoid `left`/`right`.
- Directional icons (arrows, chevrons) from `Icons` mirror automatically.
  Custom directional artwork must check `Directionality.of(context)`.
- Force LTR only for content that is inherently LTR: email addresses, phone
  numbers, and URLs (`DoryTextField(textDirection: TextDirection.ltr)`).
- **Never apply letter spacing to Arabic text**, because it breaks letter
  joining.
- Plan for Arabic strings that are longer than English. The layout smoke test
  renders every screen in both languages on a 360×640 phone.

## Adding or changing a string

1. Add the key to `app_en.arb` **with an `@key` description** (required, so
   translators have context):
   ```json
   "queueJoinButton": "Join queue",
   "@queueJoinButton": { "description": "Button that adds the user to a queue." }
   ```
2. Add the Arabic translation to `app_ar.arb` under the same key.
3. Use it: `Text(context.l10n.queueJoinButton)`.
4. Run `flutter test`. `test/app/localization/arb_parity_test.dart` fails if
   a key is missing from either file, a value is empty, a description is
   missing, or an Arabic value has no Arabic script.

Placeholders and plurals use ICU syntax, for example
`"ticketsAhead": "{count, plural, =0{You're next} =1{1 person ahead} other{{count} people ahead}}"`
with a `placeholders` entry in the metadata. Arabic has six plural categories
(`zero`, `one`, `two`, `few`, `many`, `other`), so provide all of them in
`app_ar.arb`.

## Key naming

`<screenOrArea><Meaning>` in camelCase: `loginTitle`, `homeSearchHint`,
`dashboardMetricWaiting`. Shared strings start with `common`
(`commonRetry`). Never reuse a key just because two English texts happen to
match. The translations may differ.
