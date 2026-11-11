import 'package:dory_app/app/localization/l10n.dart';
import 'package:dory_app/app/router/app_routes.dart';
import 'package:dory_app/app/theme/theme.dart';
import 'package:dory_app/core/errors/app_failure.dart';
import 'package:dory_app/core/widgets/widgets.dart';
import 'package:dory_app/features/authentication/presentation/providers/auth_controller.dart';
import 'package:dory_app/features/authentication/presentation/providers/auth_state.dart';
import 'package:dory_app/features/businesses/presentation/widgets/business_labels.dart';
import 'package:dory_app/features/team/domain/entities/team_entities.dart';
import 'package:dory_app/features/team/presentation/providers/team_providers.dart';
import 'package:dory_app/shared/components/confirm_dialog.dart';
import 'package:dory_app/shared/components/failure_message.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:intl/intl.dart' show DateFormat;
import 'package:material_ui/material_ui.dart';

/// The target of an invitation link (`dory://app/invitations/<token>`).
/// Shows what the invitation is about to anyone holding the link; only the
/// invited account can accept or decline it.
class InvitationDetailsPage extends ConsumerStatefulWidget {
  const InvitationDetailsPage({super.key, required this.token});

  final String token;

  @override
  ConsumerState<InvitationDetailsPage> createState() =>
      _InvitationDetailsPageState();
}

class _InvitationDetailsPageState extends ConsumerState<InvitationDetailsPage> {
  bool _busy = false;
  String? _error;

  Future<void> _accept(InvitationPreview preview) async {
    await _run(() async {
      final String businessId = await ref
          .read(teamActionsProvider)
          .accept(widget.token);
      if (mounted) {
        showResultMessage(
          context,
          context.l10n.invitationAccepted(preview.businessName),
        );
        context.go(AppRoutes.business(businessId));
      }
    });
  }

  Future<void> _decline() async {
    final AppLocalizations l10n = context.l10n;
    final bool confirmed = await confirmAction(
      context,
      title: l10n.invitationDeclineConfirmTitle,
      message: l10n.invitationDeclineConfirmMessage,
      confirmLabel: l10n.invitationDecline,
    );
    if (!confirmed) {
      return;
    }
    await _run(() async {
      await ref.read(teamActionsProvider).decline(widget.token);
      if (mounted) {
        showResultMessage(context, l10n.invitationDeclined);
      }
    });
  }

  Future<void> _run(Future<void> Function() action) async {
    setState(() {
      _busy = true;
      _error = null;
    });
    try {
      await action();
    } on AppFailure catch (failure) {
      if (mounted) {
        setState(() => _error = _failureText(context.l10n, failure));
      }
    } finally {
      if (mounted) {
        setState(() => _busy = false);
      }
    }
  }

  String _failureText(AppLocalizations l10n, Object failure) {
    if (failure is AppFailure) {
      switch (failure.code) {
        case 'invitation_not_found':
          return l10n.invitationNotFound;
        case 'invitation_expired':
          return l10n.invitationExpiredMessage;
        case 'invitation_not_pending':
          return l10n.invitationNotOpen;
        case 'invitation_email_mismatch':
          return l10n.invitationWrongAccount;
        case 'membership_exists':
          return l10n.inviteErrorMember;
        // The business's plan has no free seat (docs/SUBSCRIPTIONS.md).
        case 'plan_limit_reached':
          return l10n.invitationNoSeat;
      }
    }
    return failureMessage(l10n, failure);
  }

  @override
  Widget build(BuildContext context) {
    final AppLocalizations l10n = context.l10n;
    final AsyncValue<InvitationPreview> preview = ref.watch(
      invitationPreviewProvider(widget.token),
    );

    return DoryPageScaffold(
      title: l10n.invitationTitle,
      maxContentWidth: AppLayout.maxFormWidth,
      body: switch (preview) {
        AsyncData(:final InvitationPreview value) => _details(l10n, value),
        AsyncError(:final Object error) => Padding(
          padding: const EdgeInsets.symmetric(vertical: AppSpacing.xxxl),
          child: DoryErrorState(
            icon: Icons.link_off_rounded,
            title: l10n.invitationTitle,
            message: _failureText(l10n, error),
            retry: DoryStatusAction(
              label: l10n.commonRetry,
              onPressed: () =>
                  ref.invalidate(invitationPreviewProvider(widget.token)),
            ),
          ),
        ),
        _ => const Padding(
          padding: EdgeInsets.symmetric(vertical: AppSpacing.xxxl),
          child: DoryLoadingIndicator(),
        ),
      },
    );
  }

  Widget _details(AppLocalizations l10n, InvitationPreview preview) {
    final TextTheme text = Theme.of(context).textTheme;
    final AuthState auth = ref.watch(authControllerProvider);
    final String date = DateFormat.yMMMd(
      Localizations.localeOf(context).toLanguageTag(),
    ).format(preview.expiresAt.toLocal());
    final Uri here = Uri.parse(AppRoutes.invitation(widget.token));
    final String? error = _error;

    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: <Widget>[
        const Icon(
          Icons.mark_email_unread_outlined,
          size: 56,
          color: AppColors.primary,
        ),
        const SizedBox(height: AppSpacing.lg),
        Text(
          l10n.invitationHeadline(preview.businessName),
          style: text.headlineSmall,
          textAlign: TextAlign.center,
        ),
        const SizedBox(height: AppSpacing.xl),
        DoryCard(
          padding: EdgeInsets.zero,
          child: Column(
            children: <Widget>[
              ListTile(
                leading: const Icon(Icons.badge_outlined),
                title: Text(
                  l10n.invitationRoleLine(roleLabel(l10n, preview.role)),
                ),
              ),
              if (preview.invitedBy != null)
                ListTile(
                  leading: const Icon(Icons.person_outline_rounded),
                  title: Text(l10n.invitationInvitedBy(preview.invitedBy!)),
                ),
              ListTile(
                leading: const Icon(Icons.alternate_email_rounded),
                title: Text(l10n.invitationSentTo(preview.maskedEmail)),
              ),
              ListTile(
                leading: const Icon(Icons.event_outlined),
                title: Text(l10n.invitationExpires(date)),
              ),
            ],
          ),
        ),
        const SizedBox(height: AppSpacing.xl),
        if (error != null) ...<Widget>[
          DoryNotice(message: error, tone: DoryNoticeTone.error),
          const SizedBox(height: AppSpacing.lg),
        ],
        ...switch (preview.status) {
          InvitationStatus.expired => <Widget>[
            DoryNotice(
              message: l10n.invitationExpiredMessage,
              tone: DoryNoticeTone.warning,
            ),
          ],
          InvitationStatus.pending when auth is! Authenticated => <Widget>[
            DoryNotice(message: l10n.invitationSignInPrompt),
            const SizedBox(height: AppSpacing.lg),
            DoryButton(
              label: l10n.welcomeLogin,
              onPressed: () =>
                  context.go(AppRoutes.withFrom(AppRoutes.login, here)),
            ),
            const SizedBox(height: AppSpacing.md),
            DoryButton(
              label: l10n.welcomeCreateAccount,
              variant: DoryButtonVariant.secondary,
              onPressed: () =>
                  context.go(AppRoutes.withFrom(AppRoutes.register, here)),
            ),
          ],
          InvitationStatus.pending when preview.canRespond == false => <Widget>[
            DoryNotice(
              message: l10n.invitationWrongAccount,
              tone: DoryNoticeTone.warning,
            ),
          ],
          InvitationStatus.pending => <Widget>[
            DoryButton(
              label: l10n.invitationAccept,
              isLoading: _busy,
              onPressed: () => _accept(preview),
            ),
            const SizedBox(height: AppSpacing.md),
            DoryButton(
              label: l10n.invitationDecline,
              variant: DoryButtonVariant.tertiary,
              onPressed: _busy ? null : _decline,
            ),
          ],
          _ => <Widget>[
            DoryNotice(
              message: l10n.invitationNotOpen,
              tone: DoryNoticeTone.warning,
            ),
          ],
        },
      ],
    );
  }
}
