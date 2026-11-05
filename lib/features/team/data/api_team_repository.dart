import 'package:dory_app/core/errors/app_failure.dart';
import 'package:dory_app/core/network/api_client.dart';
import 'package:dory_app/core/network/api_envelope.dart';
import 'package:dory_app/core/network/json.dart';
import 'package:dory_app/features/team/domain/entities/team_entities.dart';
import 'package:dory_app/features/team/domain/repositories/team_repository.dart';
import 'package:dory_app/shared/enums/business_role.dart';

/// [TeamRepository] over the DORY API.
class ApiTeamRepository implements TeamRepository {
  const ApiTeamRepository(this._api);

  final ApiClient _api;

  static List<BranchRef> _branches(JsonMap json) => <BranchRef>[
    for (final JsonMap branch in json.objectList('branches'))
      BranchRef(id: branch.string('id'), name: branch.string('name')),
  ];

  static TeamMember member(JsonMap json) {
    final JsonMap user = json.object('user');
    final JsonMap permissions =
        json.objectOrNull('permissions') ?? const <String, Object?>{};
    return TeamMember(
      id: json.string('id'),
      name: user.string('name'),
      email: user.string('email'),
      role: BusinessRole.fromApi(json.stringOrNull('role')),
      allBranches: json.stringOrNull('branch_access') != 'selected',
      branches: _branches(json),
      isYou: json.boolean('is_you'),
      canUpdate: permissions.boolean('update'),
      canRemove: permissions.boolean('remove'),
    );
  }

  static TeamInvitation invitation(JsonMap json) => TeamInvitation(
    id: json.string('id'),
    email: json.string('email'),
    role: BusinessRole.fromApi(json.stringOrNull('role')),
    status: InvitationStatus.fromApi(json.stringOrNull('status')),
    expiresAt: json.dateTimeOrNull('expires_at') ?? DateTime.now(),
    allBranches: json.stringOrNull('branch_access') != 'selected',
    branches: _branches(json),
  );

  @override
  Future<List<TeamMember>> members(String businessId) =>
      AppFailure.guard(() async {
        final ApiEnvelope envelope = await _api.get(
          '/businesses/$businessId/members',
          query: <String, Object?>{'per_page': 100},
        );
        return envelope.dataList.map(member).toList();
      });

  @override
  Future<TeamMember> changeRole(
    String businessId,
    String memberId,
    BusinessRole role,
  ) => AppFailure.guard(() async {
    final ApiEnvelope envelope = await _api.put(
      '/businesses/$businessId/members/$memberId',
      body: <String, Object?>{'role': role.apiValue},
    );
    return member(envelope.dataObject);
  });

  @override
  Future<void> removeMember(String businessId, String memberId) =>
      AppFailure.guard(() async {
        await _api.delete('/businesses/$businessId/members/$memberId');
      });

  @override
  Future<List<TeamInvitation>> invitations(String businessId) =>
      AppFailure.guard(() async {
        final ApiEnvelope envelope = await _api.get(
          '/businesses/$businessId/invitations',
          query: <String, Object?>{'per_page': 100},
        );
        return envelope.dataList.map(invitation).toList();
      });

  @override
  Future<CreatedInvitation> invite(
    String businessId, {
    required String email,
    required BusinessRole role,
    List<String> branchIds = const <String>[],
  }) => AppFailure.guard(() async {
    final ApiEnvelope envelope = await _api.post(
      '/businesses/$businessId/invitations',
      body: <String, Object?>{
        'email': email.trim().toLowerCase(),
        'role': role.apiValue,
        if (branchIds.isNotEmpty) ...<String, Object?>{
          'branch_access': 'selected',
          'branches': branchIds,
        },
      },
    );
    final JsonMap data = envelope.dataObject;
    final String? delivery = data
        .objectOrNull('delivery')
        ?.stringOrNull('status');
    return CreatedInvitation(
      invitation: invitation(data.object('invitation')),
      delivery: switch (delivery) {
        'sent' => InvitationDelivery.sent,
        'failed' => InvitationDelivery.failed,
        _ => InvitationDelivery.notConfigured,
      },
      shareUrl: data.stringOrNull('share_url'),
    );
  });

  @override
  Future<void> revokeInvitation(String businessId, String invitationId) =>
      AppFailure.guard(() async {
        await _api.delete('/businesses/$businessId/invitations/$invitationId');
      });

  @override
  Future<InvitationPreview> preview(String token) => AppFailure.guard(() async {
    final JsonMap data = (await _api.get('/invitations/$token')).dataObject;
    final JsonMap business = data.object('business');
    return InvitationPreview(
      businessId: business.string('id'),
      businessName: business.string('name'),
      role: BusinessRole.fromApi(data.stringOrNull('role')),
      maskedEmail: data.stringOrNull('email') ?? '',
      status: InvitationStatus.fromApi(data.stringOrNull('status')),
      expiresAt: data.dateTimeOrNull('expires_at') ?? DateTime.now(),
      invitedBy: data.objectOrNull('invited_by')?.stringOrNull('name'),
      canRespond: data['can_respond'] is bool
          ? data['can_respond']! as bool
          : null,
    );
  });

  @override
  Future<String> accept(String token) => AppFailure.guard(() async {
    final ApiEnvelope envelope = await _api.post('/invitations/$token/accept');
    return envelope.dataObject.string('id');
  });

  @override
  Future<void> decline(String token) => AppFailure.guard(() async {
    await _api.post('/invitations/$token/decline');
  });
}
