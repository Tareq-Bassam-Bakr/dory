import 'package:dory_app/shared/enums/business_role.dart';
import 'package:flutter/foundation.dart';

/// A branch reference inside team data.
@immutable
class BranchRef {
  const BranchRef({required this.id, required this.name});

  final String id;
  final String name;
}

/// A member of a business team.
@immutable
class TeamMember {
  const TeamMember({
    required this.id,
    required this.name,
    required this.email,
    required this.role,
    required this.allBranches,
    this.branches = const <BranchRef>[],
    this.isYou = false,
    this.canUpdate = false,
    this.canRemove = false,
  });

  /// Membership public id.
  final String id;
  final String name;
  final String email;
  final BusinessRole role;
  final bool allBranches;
  final List<BranchRef> branches;
  final bool isYou;
  final bool canUpdate;
  final bool canRemove;
}

enum InvitationStatus {
  pending,
  accepted,
  declined,
  revoked,
  expired;

  static InvitationStatus fromApi(String? value) => values.firstWhere(
    (status) => status.name == value,
    orElse: () => expired,
  );
}

/// An invitation as listed for the business team (never with its token).
@immutable
class TeamInvitation {
  const TeamInvitation({
    required this.id,
    required this.email,
    required this.role,
    required this.status,
    required this.expiresAt,
    this.allBranches = true,
    this.branches = const <BranchRef>[],
  });

  final String id;
  final String email;
  final BusinessRole role;
  final InvitationStatus status;
  final DateTime expiresAt;
  final bool allBranches;
  final List<BranchRef> branches;
}

/// How the server delivered a new invitation.
enum InvitationDelivery { sent, notConfigured, failed }

/// A just-created invitation. [shareUrl] holds the secret link (only
/// returned when the server allows manual sharing).
@immutable
class CreatedInvitation {
  const CreatedInvitation({
    required this.invitation,
    required this.delivery,
    this.shareUrl,
  });

  final TeamInvitation invitation;
  final InvitationDelivery delivery;
  final String? shareUrl;
}

/// What the holder of an invitation link sees.
@immutable
class InvitationPreview {
  const InvitationPreview({
    required this.businessId,
    required this.businessName,
    required this.role,
    required this.maskedEmail,
    required this.status,
    required this.expiresAt,
    this.invitedBy,
    this.canRespond,
  });

  final String businessId;
  final String businessName;
  final BusinessRole role;
  final String maskedEmail;
  final InvitationStatus status;
  final DateTime expiresAt;
  final String? invitedBy;

  /// Null when signed out; false when signed in with another account.
  final bool? canRespond;
}
