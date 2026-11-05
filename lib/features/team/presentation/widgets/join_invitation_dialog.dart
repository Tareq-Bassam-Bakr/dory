import 'package:dory_app/app/localization/l10n.dart';
import 'package:dory_app/app/router/app_routes.dart';
import 'package:dory_app/core/widgets/widgets.dart';
import 'package:dory_app/features/team/presentation/providers/team_providers.dart';
import 'package:go_router/go_router.dart';
import 'package:material_ui/material_ui.dart';

/// Lets the user paste an invitation link (or code) shared with them by
/// hand, then opens the invitation.
Future<void> showJoinInvitationDialog(BuildContext context) async {
  final String? token = await showDialog<String>(
    context: context,
    builder: (_) => const _JoinInvitationDialog(),
  );
  if (token != null && context.mounted) {
    await context.push(AppRoutes.invitation(token));
  }
}

class _JoinInvitationDialog extends StatefulWidget {
  const _JoinInvitationDialog();

  @override
  State<_JoinInvitationDialog> createState() => _JoinInvitationDialogState();
}

class _JoinInvitationDialogState extends State<_JoinInvitationDialog> {
  final GlobalKey<FormState> _formKey = GlobalKey<FormState>();
  final TextEditingController _link = TextEditingController();

  @override
  void dispose() {
    _link.dispose();
    super.dispose();
  }

  void _open() {
    if (_formKey.currentState?.validate() ?? false) {
      Navigator.of(context).pop(invitationTokenFrom(_link.text));
    }
  }

  @override
  Widget build(BuildContext context) {
    final AppLocalizations l10n = context.l10n;
    return AlertDialog(
      title: Text(l10n.joinInvitationTitle),
      content: Form(
        key: _formKey,
        child: DoryTextField(
          label: l10n.joinInvitationHint,
          controller: _link,
          textDirection: TextDirection.ltr,
          keyboardType: TextInputType.url,
          validator: (value) => invitationTokenFrom(value ?? '') == null
              ? l10n.joinInvitationInvalid
              : null,
          onFieldSubmitted: (_) => _open(),
        ),
      ),
      actions: <Widget>[
        TextButton(
          onPressed: () => Navigator.of(context).pop(),
          child: Text(l10n.commonCancel),
        ),
        FilledButton(onPressed: _open, child: Text(l10n.joinInvitationOpen)),
      ],
    );
  }
}
