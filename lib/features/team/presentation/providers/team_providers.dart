import 'package:dory_app/core/network/network_providers.dart';
import 'package:dory_app/features/authentication/presentation/providers/auth_controller.dart';
import 'package:dory_app/features/businesses/presentation/providers/business_providers.dart';
import 'package:dory_app/features/team/data/api_team_repository.dart';
import 'package:dory_app/features/team/domain/entities/team_entities.dart';
import 'package:dory_app/features/team/domain/repositories/team_repository.dart';
import 'package:dory_app/shared/enums/business_role.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

final Provider<TeamRepository> teamRepositoryProvider =
    Provider<TeamRepository>(
      (ref) => ApiTeamRepository(ref.watch(apiClientProvider)),
      name: 'teamRepositoryProvider',
    );

final teamMembersProvider = FutureProvider.autoDispose
    .family<List<TeamMember>, String>((ref, businessId) {
      ref.watch(currentUserIdProvider);
      return ref.watch(teamRepositoryProvider).members(businessId);
    }, name: 'teamMembersProvider');

final teamInvitationsProvider = FutureProvider.autoDispose
    .family<List<TeamInvitation>, String>((ref, businessId) {
      ref.watch(currentUserIdProvider);
      return ref.watch(teamRepositoryProvider).invitations(businessId);
    }, name: 'teamInvitationsProvider');

/// The invitation behind a link. Re-read when the session changes, so
/// `can_respond` matches the signed-in account.
final invitationPreviewProvider = FutureProvider.autoDispose
    .family<InvitationPreview, String>((ref, token) {
      ref.watch(currentUserIdProvider);
      return ref.watch(teamRepositoryProvider).preview(token);
    }, name: 'invitationPreviewProvider');

/// Team mutations. Each refreshes the lists it affects.
class TeamActions {
  const TeamActions(this._ref);

  final Ref _ref;

  TeamRepository get _repository => _ref.read(teamRepositoryProvider);

  Future<void> changeRole(
    String businessId,
    String memberId,
    BusinessRole role,
  ) async {
    await _repository.changeRole(businessId, memberId, role);
    _ref.invalidate(teamMembersProvider(businessId));
  }

  Future<void> remove(String businessId, String memberId) async {
    await _repository.removeMember(businessId, memberId);
    _ref
      ..invalidate(teamMembersProvider(businessId))
      ..invalidate(businessProvider(businessId));
  }

  Future<CreatedInvitation> invite(
    String businessId, {
    required String email,
    required BusinessRole role,
    List<String> branchIds = const <String>[],
  }) async {
    final CreatedInvitation created = await _repository.invite(
      businessId,
      email: email,
      role: role,
      branchIds: branchIds,
    );
    _ref.invalidate(teamInvitationsProvider(businessId));
    return created;
  }

  Future<void> revoke(String businessId, String invitationId) async {
    await _repository.revokeInvitation(businessId, invitationId);
    _ref.invalidate(teamInvitationsProvider(businessId));
  }

  /// Joins the business; returns its id.
  Future<String> accept(String token) async {
    final String businessId = await _repository.accept(token);
    _ref
      ..invalidate(invitationPreviewProvider(token))
      ..invalidate(myBusinessesProvider);
    await _ref.read(authControllerProvider.notifier).refreshUser();
    return businessId;
  }

  Future<void> decline(String token) async {
    await _repository.decline(token);
    _ref.invalidate(invitationPreviewProvider(token));
  }
}

final Provider<TeamActions> teamActionsProvider = Provider<TeamActions>(
  TeamActions.new,
  name: 'teamActionsProvider',
);

/// Extracts an invitation token from a pasted link or code
/// (`dory://app/invitations/<token>`, an https link, or the token alone).
String? invitationTokenFrom(String input) {
  final RegExpMatch? match = RegExp(
    r'(?:^|/)([A-Za-z0-9]{64})(?:[/?#]|$)',
  ).firstMatch(input.trim());
  return match?.group(1);
}
