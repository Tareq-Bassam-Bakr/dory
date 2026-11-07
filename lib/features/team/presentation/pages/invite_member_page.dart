import 'package:dory_app/app/localization/l10n.dart';
import 'package:dory_app/app/router/app_routes.dart';
import 'package:dory_app/app/theme/theme.dart';
import 'package:dory_app/core/errors/app_failure.dart';
import 'package:dory_app/core/widgets/widgets.dart';
import 'package:dory_app/features/authentication/presentation/validation/auth_validators.dart';
import 'package:dory_app/features/branch_management/domain/entities/branch.dart';
import 'package:dory_app/features/branch_management/presentation/providers/branch_providers.dart';
import 'package:dory_app/features/team/domain/entities/team_entities.dart';
import 'package:dory_app/features/team/presentation/providers/team_providers.dart';
import 'package:dory_app/shared/components/failure_message.dart';
import 'package:dory_app/shared/enums/business_role.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:material_ui/material_ui.dart';

/// Invites a manager or employee by email, optionally limited to some
/// branches. Afterwards it says honestly how the invitation was delivered
/// and, when the server allows it, shows the link for manual sharing.
class InviteMemberPage extends ConsumerStatefulWidget {
  const InviteMemberPage({super.key, required this.businessId});

  final String businessId;

  @override
  ConsumerState<InviteMemberPage> createState() => _InviteMemberPageState();
}

class _InviteMemberPageState extends ConsumerState<InviteMemberPage> {
  final GlobalKey<FormState> _formKey = GlobalKey<FormState>();
  final TextEditingController _email = TextEditingController();
  BusinessRole _role = BusinessRole.employee;
  bool _allBranches = true;
  final Set<String> _branchIds = <String>{};
  bool _sending = false;

  /// "Selected branches" with none ticked.
  bool _branchesMissing = false;
  AppFailure? _failure;
  CreatedInvitation? _created;

  @override
  void dispose() {
    _email.dispose();
    super.dispose();
  }

  Future<void> _send() async {
    if (_sending || !(_formKey.currentState?.validate() ?? false)) {
      return;
    }
    final bool noBranchChosen = !_allBranches && _branchIds.isEmpty;
    setState(() => _branchesMissing = noBranchChosen);
    if (noBranchChosen) {
      return;
    }
    setState(() {
      _sending = true;
      _failure = null;
    });
    try {
      final CreatedInvitation created = await ref
          .read(teamActionsProvider)
          .invite(
            widget.businessId,
            email: _email.text,
            role: _role,
            branchIds: _allBranches ? const <String>[] : _branchIds.toList(),
          );
      if (mounted) {
        setState(() => _created = created);
      }
    } on AppFailure catch (failure) {
      if (mounted) {
        setState(() => _failure = failure);
      }
    } finally {
      if (mounted) {
        setState(() => _sending = false);
      }
    }
  }

  String _failureText(AppLocalizations l10n, AppFailure failure) =>
      switch (failure.code) {
        'invitation_pending' => l10n.inviteErrorPending,
        'membership_exists' => l10n.inviteErrorMember,
        _ => failureMessage(l10n, failure),
      };

  @override
  Widget build(BuildContext context) {
    final AppLocalizations l10n = context.l10n;
    final CreatedInvitation? created = _created;
    return DoryPageScaffold(
      title: l10n.inviteTitle,
      maxContentWidth: AppLayout.maxFormWidth,
      body: created == null ? _form(l10n) : _result(l10n, created),
    );
  }

  Widget _form(AppLocalizations l10n) {
    final AppFailure? failure = _failure;
    final AsyncValue<List<Branch>> branches = ref.watch(
      branchesProvider(widget.businessId),
    );

    return Form(
      key: _formKey,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: <Widget>[
          if (failure != null) ...<Widget>[
            DoryNotice(
              message: _failureText(l10n, failure),
              tone: DoryNoticeTone.error,
            ),
            const SizedBox(height: AppSpacing.lg),
          ],
          DoryTextField(
            label: l10n.inviteEmailLabel,
            controller: _email,
            prefixIcon: Icons.mail_outline_rounded,
            keyboardType: TextInputType.emailAddress,
            textDirection: TextDirection.ltr,
            enabled: !_sending,
            errorText: failure?.fieldErrors['email'],
            validator: (value) => AuthValidators.email(l10n, value),
          ),
          const SizedBox(height: AppSpacing.xl),
          DorySectionHeader(title: l10n.inviteRoleLabel),
          RadioGroup<BusinessRole>(
            groupValue: _role,
            onChanged: (role) => setState(() => _role = role ?? _role),
            child: DoryCard(
              padding: EdgeInsets.zero,
              child: Column(
                children: <Widget>[
                  RadioListTile<BusinessRole>(
                    value: BusinessRole.employee,
                    title: Text(l10n.businessRoleEmployee),
                    subtitle: Text(l10n.inviteRoleEmployeeHint),
                  ),
                  RadioListTile<BusinessRole>(
                    value: BusinessRole.manager,
                    title: Text(l10n.businessRoleManager),
                    subtitle: Text(l10n.inviteRoleManagerHint),
                  ),
                ],
              ),
            ),
          ),
          const SizedBox(height: AppSpacing.xl),
          DorySectionHeader(title: l10n.inviteAccessLabel),
          DoryCard(
            padding: EdgeInsets.zero,
            child: Column(
              children: <Widget>[
                SwitchListTile(
                  title: Text(l10n.inviteAccessAll),
                  value: _allBranches,
                  onChanged: _sending
                      ? null
                      : (value) => setState(() => _allBranches = value),
                ),
                if (!_allBranches)
                  for (final Branch branch
                      in branches.value ?? const <Branch>[])
                    CheckboxListTile(
                      title: Text(branch.name),
                      value: _branchIds.contains(branch.id),
                      onChanged: _sending
                          ? null
                          : (checked) => setState(
                              () => checked ?? false
                                  ? _branchIds.add(branch.id)
                                  : _branchIds.remove(branch.id),
                            ),
                    ),
              ],
            ),
          ),
          if (_branchesMissing &&
              !_allBranches &&
              _branchIds.isEmpty) ...<Widget>[
            const SizedBox(height: AppSpacing.md),
            DoryNotice(
              message: l10n.inviteSelectBranches,
              tone: DoryNoticeTone.error,
            ),
          ],
          const SizedBox(height: AppSpacing.xl),
          DoryButton(
            label: l10n.inviteSubmit,
            isLoading: _sending,
            onPressed: _send,
          ),
        ],
      ),
    );
  }

  Widget _result(AppLocalizations l10n, CreatedInvitation created) {
    final String email = created.invitation.email;
    final String? link = created.shareUrl;
    final (String message, DoryNoticeTone tone) = switch (created.delivery) {
      InvitationDelivery.sent => (
        l10n.inviteDeliverySent(email),
        DoryNoticeTone.success,
      ),
      InvitationDelivery.notConfigured => (
        link == null
            ? l10n.inviteNoShareLink
            : l10n.inviteDeliveryNotConfigured(email),
        DoryNoticeTone.warning,
      ),
      InvitationDelivery.failed => (
        link == null
            ? l10n.inviteNoShareLink
            : l10n.inviteDeliveryFailed(email),
        DoryNoticeTone.warning,
      ),
    };

    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: <Widget>[
        Text(
          l10n.inviteCreatedTitle,
          style: Theme.of(context).textTheme.headlineSmall,
        ),
        const SizedBox(height: AppSpacing.lg),
        DoryNotice(message: message, tone: tone),
        if (link != null) ...<Widget>[
          const SizedBox(height: AppSpacing.xl),
          DorySectionHeader(title: l10n.inviteShareLinkLabel),
          DoryCard(
            child: SelectableText(link, textDirection: TextDirection.ltr),
          ),
          const SizedBox(height: AppSpacing.sm),
          Text(
            l10n.inviteShareWarning,
            style: Theme.of(context).textTheme.bodySmall,
          ),
          const SizedBox(height: AppSpacing.md),
          DoryButton(
            label: l10n.commonCopy,
            icon: Icons.copy_rounded,
            variant: DoryButtonVariant.secondary,
            onPressed: () async {
              await Clipboard.setData(ClipboardData(text: link));
              if (mounted) {
                ScaffoldMessenger.of(
                  context,
                ).showSnackBar(SnackBar(content: Text(l10n.commonCopied)));
              }
            },
          ),
        ],
        const SizedBox(height: AppSpacing.xl),
        DoryButton(
          label: l10n.inviteDone,
          onPressed: () => context.go(AppRoutes.team(widget.businessId)),
        ),
      ],
    );
  }
}
