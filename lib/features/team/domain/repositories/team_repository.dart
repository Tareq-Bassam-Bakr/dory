import 'package:dory_app/features/team/domain/entities/team_entities.dart';
import 'package:dory_app/shared/enums/business_role.dart';

/// Team members and invitations. Every method throws [AppFailure].
abstract interface class TeamRepository {
  Future<List<TeamMember>> members(String businessId);

  Future<TeamMember> changeRole(
    String businessId,
    String memberId,
    BusinessRole role,
  );

  Future<void> removeMember(String businessId, String memberId);

  Future<List<TeamInvitation>> invitations(String businessId);

  /// [branchIds] empty means access to all branches.
  Future<CreatedInvitation> invite(
    String businessId, {
    required String email,
    required BusinessRole role,
    List<String> branchIds = const <String>[],
  });

  Future<void> revokeInvitation(String businessId, String invitationId);

  Future<InvitationPreview> preview(String token);

  /// Returns the id of the business joined.
  Future<String> accept(String token);

  Future<void> decline(String token);
}
