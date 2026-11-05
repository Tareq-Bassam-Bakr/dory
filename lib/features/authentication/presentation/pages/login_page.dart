import 'package:dory_app/app/localization/l10n.dart';
import 'package:dory_app/app/router/app_routes.dart';
import 'package:dory_app/app/theme/theme.dart';
import 'package:dory_app/core/widgets/widgets.dart';
import 'package:dory_app/features/authentication/domain/entities/auth_failure.dart';
import 'package:dory_app/features/authentication/presentation/auth_failure_messages.dart';
import 'package:dory_app/features/authentication/presentation/providers/auth_controller.dart';
import 'package:dory_app/features/authentication/presentation/providers/auth_state.dart';
import 'package:dory_app/features/authentication/presentation/validation/auth_validators.dart';
import 'package:dory_app/features/authentication/presentation/widgets/auth_header.dart';
import 'package:dory_app/features/authentication/presentation/widgets/auth_switch_prompt.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:material_ui/material_ui.dart';

/// Email + password login.
///
/// On success the router leaves this page by itself: to `?from=` or to the
/// user's home workspace (see authRedirect).
class LoginPage extends ConsumerStatefulWidget {
  const LoginPage({super.key, this.from});

  /// Where to continue after login (already validated by the router).
  final String? from;

  @override
  ConsumerState<LoginPage> createState() => _LoginPageState();
}

class _LoginPageState extends ConsumerState<LoginPage> {
  final GlobalKey<FormState> _formKey = GlobalKey<FormState>();
  final TextEditingController _email = TextEditingController();
  final TextEditingController _password = TextEditingController();
  bool _submitting = false;
  String? _error;
  Map<String, String> _fieldErrors = const <String, String>{};

  @override
  void dispose() {
    _email.dispose();
    _password.dispose();
    super.dispose();
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

    setState(() => _submitting = true);
    try {
      await ref
          .read(authControllerProvider.notifier)
          .login(email: _email.text, password: _password.text);
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
    final AuthState auth = ref.watch(authControllerProvider);
    final bool sessionExpired =
        auth is Unauthenticated && auth.sessionExpired && _error == null;
    final String? error = _error;

    return DoryPageScaffold(
      maxContentWidth: AppLayout.maxFormWidth,
      body: AutofillGroup(
        child: Form(
          key: _formKey,
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: <Widget>[
              AuthHeader(title: l10n.loginTitle, subtitle: l10n.loginSubtitle),
              const SizedBox(height: AppSpacing.xl),
              if (sessionExpired) ...<Widget>[
                DoryNotice(
                  message: l10n.authSessionExpired,
                  tone: DoryNoticeTone.warning,
                ),
                const SizedBox(height: AppSpacing.lg),
              ],
              if (error != null) ...<Widget>[
                DoryNotice(message: error, tone: DoryNoticeTone.error),
                const SizedBox(height: AppSpacing.lg),
              ],
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
                validator: (value) => AuthValidators.email(l10n, value),
              ),
              const SizedBox(height: AppSpacing.lg),
              DoryTextField(
                label: l10n.authPasswordLabel,
                controller: _password,
                prefixIcon: Icons.lock_outline_rounded,
                textInputAction: TextInputAction.done,
                autofillHints: const <String>[AutofillHints.password],
                enabled: !_submitting,
                errorText: _fieldErrors['password'],
                validator: (value) => AuthValidators.loginPassword(l10n, value),
                onFieldSubmitted: (_) => _submit(),
                passwordVisibilityLabels: PasswordVisibilityLabels(
                  show: l10n.authShowPassword,
                  hide: l10n.authHidePassword,
                ),
              ),
              const SizedBox(height: AppSpacing.xl),
              DoryButton(
                label: l10n.loginSubmit,
                isLoading: _submitting,
                onPressed: _submit,
              ),
              const SizedBox(height: AppSpacing.lg),
              AuthSwitchPrompt(
                prompt: l10n.loginNoAccountPrompt,
                actionLabel: l10n.welcomeCreateAccount,
                onPressed: () => context.go(
                  widget.from == null
                      ? AppRoutes.register
                      : AppRoutes.withFrom(
                          AppRoutes.register,
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
