import 'package:dory_app/app/localization/l10n.dart';
import 'package:dory_app/app/router/app_routes.dart';
import 'package:dory_app/app/theme/theme.dart';
import 'package:dory_app/core/widgets/widgets.dart';
import 'package:dory_app/features/authentication/presentation/widgets/auth_header.dart';
import 'package:dory_app/features/authentication/presentation/widgets/auth_switch_prompt.dart';
import 'package:go_router/go_router.dart';
import 'package:material_ui/material_ui.dart';

/// Registration screen layout. Account creation is not implemented in
/// Phase 01: the submit button is disabled and a notice explains why.
///
/// The field set is provisional until the authentication API contract is
/// defined in Phase 02.
class RegisterPage extends StatelessWidget {
  const RegisterPage({super.key});

  @override
  Widget build(BuildContext context) {
    final AppLocalizations l10n = context.l10n;

    return DoryPageScaffold(
      maxContentWidth: AppLayout.maxFormWidth,
      body: AutofillGroup(
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: <Widget>[
            AuthHeader(
              title: l10n.registerTitle,
              subtitle: l10n.registerSubtitle,
            ),
            const SizedBox(height: AppSpacing.xl),
            DoryNotice(message: l10n.authUnavailableNotice),
            const SizedBox(height: AppSpacing.xl),
            DoryTextField(
              label: l10n.authFullNameLabel,
              prefixIcon: Icons.person_outline_rounded,
              keyboardType: TextInputType.name,
              textInputAction: TextInputAction.next,
              autofillHints: const <String>[AutofillHints.name],
            ),
            const SizedBox(height: AppSpacing.lg),
            DoryTextField(
              label: l10n.authEmailLabel,
              prefixIcon: Icons.mail_outline_rounded,
              keyboardType: TextInputType.emailAddress,
              textInputAction: TextInputAction.next,
              autofillHints: const <String>[AutofillHints.email],
              textDirection: TextDirection.ltr,
            ),
            const SizedBox(height: AppSpacing.lg),
            DoryTextField(
              label: l10n.authPhoneLabel,
              prefixIcon: Icons.phone_outlined,
              keyboardType: TextInputType.phone,
              textInputAction: TextInputAction.next,
              autofillHints: const <String>[AutofillHints.telephoneNumber],
              textDirection: TextDirection.ltr,
            ),
            const SizedBox(height: AppSpacing.lg),
            DoryTextField(
              label: l10n.authPasswordLabel,
              prefixIcon: Icons.lock_outline_rounded,
              textInputAction: TextInputAction.done,
              autofillHints: const <String>[AutofillHints.newPassword],
              passwordVisibilityLabels: PasswordVisibilityLabels(
                show: l10n.authShowPassword,
                hide: l10n.authHidePassword,
              ),
            ),
            const SizedBox(height: AppSpacing.xl),
            DoryButton(
              label: l10n.registerSubmit,
              // Enabled once real account creation exists (Phase 02).
              onPressed: null,
            ),
            const SizedBox(height: AppSpacing.lg),
            AuthSwitchPrompt(
              prompt: l10n.registerHaveAccountPrompt,
              actionLabel: l10n.welcomeLogin,
              onPressed: () => context.go(AppRoutes.login),
            ),
          ],
        ),
      ),
    );
  }
}
