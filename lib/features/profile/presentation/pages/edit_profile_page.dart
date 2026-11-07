import 'package:dory_app/app/localization/l10n.dart';
import 'package:dory_app/app/theme/theme.dart';
import 'package:dory_app/core/errors/app_failure.dart';
import 'package:dory_app/core/widgets/widgets.dart';
import 'package:dory_app/features/authentication/domain/entities/auth_user.dart';
import 'package:dory_app/features/authentication/presentation/providers/auth_controller.dart';
import 'package:dory_app/features/authentication/presentation/providers/auth_state.dart';
import 'package:dory_app/features/profile/data/api_profile_repository.dart';
import 'package:dory_app/shared/components/confirm_dialog.dart';
import 'package:dory_app/shared/components/failure_message.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:material_ui/material_ui.dart';

/// Name and optional phone number of the signed-in customer. The email is
/// the sign-in identity and cannot be changed here.
class EditProfilePage extends ConsumerStatefulWidget {
  const EditProfilePage({super.key});

  @override
  ConsumerState<EditProfilePage> createState() => _EditProfilePageState();
}

class _EditProfilePageState extends ConsumerState<EditProfilePage> {
  final GlobalKey<FormState> _form = GlobalKey<FormState>();
  late final TextEditingController _name = TextEditingController(
    text: _user?.name,
  );
  late final TextEditingController _phone = TextEditingController(
    text: _user?.phone,
  );

  bool _saving = false;
  Map<String, String> _fieldErrors = const <String, String>{};

  AuthUser? get _user {
    final AuthState auth = ref.read(authControllerProvider);
    return auth is Authenticated ? auth.user : null;
  }

  @override
  void dispose() {
    _name.dispose();
    _phone.dispose();
    super.dispose();
  }

  Future<void> _save() async {
    final AppLocalizations l10n = context.l10n;
    if (!(_form.currentState?.validate() ?? false)) {
      return;
    }
    setState(() {
      _saving = true;
      _fieldErrors = const <String, String>{};
    });
    try {
      await ref
          .read(profileRepositoryProvider)
          .update(name: _name.text, phone: _phone.text);
      await ref.read(authControllerProvider.notifier).refreshUser();
      if (!mounted) {
        return;
      }
      showResultMessage(context, l10n.commonSaved);
      context.pop();
    } on AppFailure catch (failure) {
      if (!mounted) {
        return;
      }
      setState(() {
        _saving = false;
        _fieldErrors = failure.fieldErrors;
      });
      if (failure.fieldErrors.isEmpty) {
        showResultMessage(context, failureMessage(l10n, failure));
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    final AppLocalizations l10n = context.l10n;
    final AuthUser? user = _user;

    return DoryPageScaffold(
      title: l10n.profileEditTitle,
      maxContentWidth: AppLayout.maxFormWidth,
      body: user == null
          ? DoryEmptyState(
              icon: Icons.person_outline_rounded,
              title: l10n.accountGuestTitle,
            )
          : Form(
              key: _form,
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: <Widget>[
                  DoryTextField(
                    controller: _name,
                    label: l10n.profileName,
                    textCapitalization: TextCapitalization.words,
                    errorText: _fieldErrors['name'],
                    validator: (value) => (value?.trim().length ?? 0) >= 2
                        ? null
                        : l10n.joinGuestNameRequired,
                  ),
                  const SizedBox(height: AppSpacing.md),
                  DoryTextField(
                    controller: _phone,
                    label: l10n.profilePhoneOptional,
                    keyboardType: TextInputType.phone,
                    textDirection: TextDirection.ltr,
                    errorText: _fieldErrors['phone'],
                  ),
                  const SizedBox(height: AppSpacing.xl),
                  DoryButton(
                    label: l10n.commonSave,
                    isLoading: _saving,
                    onPressed: _save,
                  ),
                ],
              ),
            ),
    );
  }
}
