import 'package:dory_app/app/localization/app_locales.dart';
import 'package:dory_app/app/localization/l10n.dart';
import 'package:dory_app/app/router/app_routes.dart';
import 'package:dory_app/app/theme/theme.dart';
import 'package:dory_app/core/widgets/widgets.dart';
import 'package:dory_app/features/authentication/domain/entities/auth_failure.dart';
import 'package:dory_app/features/authentication/domain/entities/registration_details.dart';
import 'package:dory_app/features/authentication/presentation/auth_failure_messages.dart';
import 'package:dory_app/features/authentication/presentation/providers/auth_controller.dart';
import 'package:dory_app/features/authentication/presentation/validation/auth_validators.dart';
import 'package:dory_app/features/authentication/presentation/widgets/auth_header.dart';
import 'package:dory_app/features/authentication/presentation/widgets/auth_switch_prompt.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:material_ui/material_ui.dart';

/// Account creation with email + password. The phone number is optional
/// profile information.
///
/// On success the user is signed in and the router continues to `?from=`
/// or the home workspace.
class RegisterPage extends ConsumerStatefulWidget {
  const RegisterPage({super.key, this.from});

  final String? from;

  @override
  ConsumerState<RegisterPage> createState() => _RegisterPageState();
}

class _RegisterPageState extends ConsumerState<RegisterPage> {
  final GlobalKey<FormState> _formKey = GlobalKey<FormState>();
  final TextEditingController _name = TextEditingController();
  final TextEditingController _email = TextEditingController();
  final TextEditingController _phone = TextEditingController();
  final TextEditingController _password = TextEditingController();
  final TextEditingController _confirmation = TextEditingController();
  bool _submitting = false;
  String? _error;
  Map<String, String> _fieldErrors = const <String, String>{};

  @override
  void dispose() {
    for (final TextEditingController controller in <TextEditingController>[
      _name,
      _email,
      _phone,
      _password,
      _confirmation,
    ]) {
      controller.dispose();
    }
    super.dispose();
  }

  void _clearFieldError(String field) {
    if (_fieldErrors.containsKey(field)) {
      setState(() {
        _fieldErrors = Map<String, String>.of(_fieldErrors)..remove(field);
      });
    }
  }

  Future<void> _submit() async {
    if (_submitting) {
      return;
    }
    FocusScope.of(context).unfocus();
    setState(() {
      _error = null;
      _fieldErrors = const <String, String>{};
    });
    if (!(_formKey.currentState?.validate() ?? false)) {
      return;
    }

    final Locale locale = Localizations.localeOf(context);
    final RegistrationDetails details = RegistrationDetails(
      name: _name.text.trim(),
      email: _email.text.trim().toLowerCase(),
      phone: AuthValidators.normalizePhone(_phone.text),
      password: _password.text,
      passwordConfirmation: _confirmation.text,
      locale: AppLocales.supported.contains(Locale(locale.languageCode))
          ? locale.languageCode
          : null,
    );

    setState(() => _submitting = true);
    try {
      await ref.read(authControllerProvider.notifier).register(details);
    } on AuthFailure catch (failure) {
      if (mounted) {
        setState(() {
          _error = authFailureMessage(context.l10n, failure);
          _fieldErrors = failure.fieldErrors;
        });
      }
    } finally {
      if (mounted) {
        setState(() => _submitting = false);
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    final AppLocalizations l10n = context.l10n;
    final String? error = _error;

    return DoryPageScaffold(
      maxContentWidth: AppLayout.maxFormWidth,
      body: AutofillGroup(
        child: Form(
          key: _formKey,
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: <Widget>[
              AuthHeader(
                title: l10n.registerTitle,
                subtitle: l10n.registerSubtitle,
              ),
              const SizedBox(height: AppSpacing.xl),
              if (error != null) ...<Widget>[
                DoryNotice(message: error, tone: DoryNoticeTone.error),
                const SizedBox(height: AppSpacing.lg),
              ],
              DoryTextField(
                label: l10n.authFullNameLabel,
                controller: _name,
                prefixIcon: Icons.person_outline_rounded,
                keyboardType: TextInputType.name,
                textInputAction: TextInputAction.next,
                textCapitalization: TextCapitalization.words,
                autofillHints: const <String>[AutofillHints.name],
                enabled: !_submitting,
                errorText: _fieldErrors['name'],
                onChanged: (_) => _clearFieldError('name'),
                validator: (value) => AuthValidators.name(l10n, value),
              ),
              const SizedBox(height: AppSpacing.lg),
              DoryTextField(
                label: l10n.authEmailLabel,
                controller: _email,
                prefixIcon: Icons.mail_outline_rounded,
                keyboardType: TextInputType.emailAddress,
                textInputAction: TextInputAction.next,
                autofillHints: const <String>[AutofillHints.email],
                textDirection: TextDirection.ltr,
                enabled: !_submitting,
                errorText: _fieldErrors['email'],
                onChanged: (_) => _clearFieldError('email'),
                validator: (value) => AuthValidators.email(l10n, value),
              ),
              const SizedBox(height: AppSpacing.lg),
              DoryTextField(
                label: l10n.authPhoneLabel,
                controller: _phone,
                prefixIcon: Icons.phone_outlined,
                keyboardType: TextInputType.phone,
                textInputAction: TextInputAction.next,
                autofillHints: const <String>[AutofillHints.telephoneNumber],
                textDirection: TextDirection.ltr,
                enabled: !_submitting,
                helperText: l10n.authPhoneHint,
                errorText: _fieldErrors['phone'],
                onChanged: (_) => _clearFieldError('phone'),
                validator: (value) => AuthValidators.optionalPhone(l10n, value),
              ),
              const SizedBox(height: AppSpacing.lg),
              DoryTextField(
                label: l10n.authPasswordLabel,
                controller: _password,
                prefixIcon: Icons.lock_outline_rounded,
                textInputAction: TextInputAction.next,
                autofillHints: const <String>[AutofillHints.newPassword],
                enabled: !_submitting,
                helperText: l10n.authPasswordRules,
                errorText: _fieldErrors['password'],
                onChanged: (_) => _clearFieldError('password'),
                validator: (value) => AuthValidators.newPassword(l10n, value),
                passwordVisibilityLabels: PasswordVisibilityLabels(
                  show: l10n.authShowPassword,
                  hide: l10n.authHidePassword,
                ),
              ),
              const SizedBox(height: AppSpacing.lg),
              DoryTextField(
                label: l10n.authConfirmPasswordLabel,
                controller: _confirmation,
                prefixIcon: Icons.lock_outline_rounded,
                textInputAction: TextInputAction.done,
                autofillHints: const <String>[AutofillHints.newPassword],
                enabled: !_submitting,
                validator: (value) =>
                    AuthValidators.confirmation(l10n, value, _password.text),
                onFieldSubmitted: (_) => _submit(),
                passwordVisibilityLabels: PasswordVisibilityLabels(
                  show: l10n.authShowPassword,
                  hide: l10n.authHidePassword,
                ),
              ),
              const SizedBox(height: AppSpacing.xl),
              DoryButton(
                label: l10n.registerSubmit,
                isLoading: _submitting,
                onPressed: _submit,
              ),
              const SizedBox(height: AppSpacing.lg),
              AuthSwitchPrompt(
                prompt: l10n.registerHaveAccountPrompt,
                actionLabel: l10n.welcomeLogin,
                onPressed: () => context.go(
                  widget.from == null
                      ? AppRoutes.login
                      : AppRoutes.withFrom(
                          AppRoutes.login,
                          Uri.parse(widget.from!),
                        ),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
