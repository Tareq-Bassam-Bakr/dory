import 'package:dory_app/app/localization/l10n.dart';
import 'package:dory_app/app/router/app_routes.dart';
import 'package:dory_app/app/theme/theme.dart';
import 'package:dory_app/core/errors/app_failure.dart';
import 'package:dory_app/core/widgets/widgets.dart';
import 'package:dory_app/features/businesses/presentation/providers/business_providers.dart';
import 'package:dory_app/features/businesses/presentation/widgets/business_labels.dart';
import 'package:dory_app/features/team/domain/entities/team_entities.dart';
import 'package:dory_app/features/team/presentation/providers/team_providers.dart';
import 'package:dory_app/shared/components/async_value_view.dart';
import 'package:dory_app/shared/components/confirm_dialog.dart';
import 'package:dory_app/shared/components/failure_message.dart';
import 'package:dory_app/shared/components/status_chip.dart';
import 'package:dory_app/shared/enums/business_role.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:intl/intl.dart' show DateFormat;
import 'package:material_ui/material_ui.dart';

/// The business team and its pending invitations. Owners change roles,
/// remove members and revoke invitations; managers see the team.
class TeamMembersPage extends ConsumerWidget {
  const TeamMembersPage({super.key, required this.businessId});

  final String businessId;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final AppLocalizations l10n = context.l10n;
    final AsyncValue<List<TeamMember>> members = ref.watch(
      teamMembersProvider(businessId),
    );
    final bool canManage =
        ref
            .watch(businessProvider(businessId))
            .value
            ?.permissions
            .manageMembers ??
        false;

    return DoryPageScaffold(
      title: l10n.businessTeam,
      body: AsyncValueView<List<TeamMember>>(
        value: members,
        onRetry: () => ref.invalidate(teamMembersProvider(businessId)),
        data: (items) => Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: <Widget>[
            if (canManage) ...<Widget>[
              DoryButton(
                label: l10n.teamInvite,
                icon: Icons.person_add_alt_1_outlined,
                onPressed: () => context.go(AppRoutes.teamInvite(businessId)),
              ),
              const SizedBox(height: AppSpacing.xl),
            ],
            DorySectionHeader(title: l10n.teamMembersSection),
            DoryCard(
              padding: EdgeInsets.zero,
              child: Column(
                children: <Widget>[
                  for (final TeamMember member in items)
                    _MemberTile(businessId: businessId, member: member),
                ],
              ),
            ),
            const SizedBox(height: AppSpacing.xl),
            DorySectionHeader(title: l10n.teamInvitationsSection),
            _PendingInvitations(businessId: businessId, canManage: canManage),
          ],
        ),
      ),
    );
  }
}

class _MemberTile extends ConsumerWidget {
  const _MemberTile({required this.businessId, required this.member});

  final String businessId;
  final TeamMember member;

  Future<void> _act(BuildContext context, WidgetRef ref, String action) async {
    final AppLocalizations l10n = context.l10n;
    try {
      switch (action) {
        case 'manager' || 'employee':
          await ref
              .read(teamActionsProvider)
              .changeRole(businessId, member.id, BusinessRole.fromApi(action));
          if (context.mounted) {
            showResultMessage(context, l10n.teamMemberUpdated);
          }
        case 'remove':
          final bool confirmed = await confirmAction(
            context,
            title: l10n.teamRemoveConfirmTitle(member.name),
            message: l10n.teamRemoveConfirmMessage,
            confirmLabel: l10n.commonRemove,
            destructive: true,
          );
          if (!confirmed) {
            return;
          }
          await ref.read(teamActionsProvider).remove(businessId, member.id);
          if (context.mounted) {
            showResultMessage(context, l10n.teamMemberRemoved);
          }
      }
    } on AppFailure catch (failure) {
      if (context.mounted) {
        showResultMessage(context, failureMessage(l10n, failure));
      }
    }
  }

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final AppLocalizations l10n = context.l10n;
    final String access = member.allBranches
        ? l10n.teamAllBranches
        : member.branches.map((b) => b.name).join(', ');

    return ListTile(
      leading: CircleAvatar(
        backgroundColor: AppColors.primaryContainer,
        foregroundColor: AppColors.primary,
        child: Text(member.name.characters.first.toUpperCase()),
      ),
      title: Row(
        children: <Widget>[
          Flexible(child: Text(member.name, overflow: TextOverflow.ellipsis)),
          if (member.isYou) ...<Widget>[
            const SizedBox(width: AppSpacing.sm),
            StatusChip(label: l10n.teamYou, tone: StatusTone.neutral),
          ],
        ],
      ),
      subtitle: Text('${roleLabel(l10n, member.role)} · $access'),
      trailing: member.canUpdate || member.canRemove
          ? PopupMenuButton<String>(
              tooltip: l10n.teamChangeRole,
              onSelected: (action) => _act(context, ref, action),
              itemBuilder: (_) => <PopupMenuEntry<String>>[
                if (member.canUpdate && member.role != BusinessRole.manager)
                  PopupMenuItem<String>(
                    value: 'manager',
                    child: Text(l10n.teamMakeManager),
                  ),
                if (member.canUpdate && member.role != BusinessRole.employee)
                  PopupMenuItem<String>(
                    value: 'employee',
                    child: Text(l10n.teamMakeEmployee),
                  ),
                if (member.canRemove)
                  PopupMenuItem<String>(
                    value: 'remove',
                    child: Text(l10n.teamRemove),
                  ),
              ],
            )
          : null,
    );
  }
}

class _PendingInvitations extends ConsumerWidget {
  const _PendingInvitations({
    required this.businessId,
    required this.canManage,
  });

  final String businessId;
  final bool canManage;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final AppLocalizations l10n = context.l10n;
    final String locale = Localizations.localeOf(context).toLanguageTag();
    final AsyncValue<List<TeamInvitation>> invitations = ref.watch(
      teamInvitationsProvider(businessId),
    );

    return AsyncValueView<List<TeamInvitation>>(
      value: invitations,
      onRetry: () => ref.invalidate(teamInvitationsProvider(businessId)),
      data: (items) => items.isEmpty
          ? Text(
              l10n.teamNoInvitations,
              style: Theme.of(context).textTheme.bodyMedium,
            )
          : DoryCard(
              padding: EdgeInsets.zero,
              child: Column(
                children: <Widget>[
                  for (final TeamInvitation invitation in items)
                    ListTile(
                      leading: const Icon(Icons.mail_outline_rounded),
                      title: Text(
                        invitation.email,
                        textDirection: TextDirection.ltr,
                      ),
                      subtitle: Text(
                        invitation.status == InvitationStatus.expired
                            ? '${roleLabel(l10n, invitation.role)} · ${l10n.invitationStatusExpired}'
                            : '${roleLabel(l10n, invitation.role)} · '
                                  '${l10n.invitationExpiresOn(DateFormat.yMMMd(locale).format(invitation.expiresAt.toLocal()))}',
                      ),
                      trailing: canManage
                          ? TextButton(
                              onPressed: () async {
                                try {
                                  await ref
                                      .read(teamActionsProvider)
                                      .revoke(businessId, invitation.id);
                                  if (context.mounted) {
                                    showResultMessage(
                                      context,
                                      l10n.invitationRevoked,
                                    );
                                  }
                                } on AppFailure catch (failure) {
                                  if (context.mounted) {
                                    showResultMessage(
                                      context,
                                      failureMessage(l10n, failure),
                                    );
                                  }
                                }
                              },
                              child: Text(l10n.invitationRevoke),
                            )
                          : null,
                    ),
                ],
              ),
            ),
    );
  }
}
