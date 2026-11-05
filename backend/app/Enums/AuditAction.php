<?php

namespace App\Enums;

/**
 * Actions recorded in business_audit_logs.
 */
enum AuditAction: string
{
    case BusinessCreated = 'business.created';
    case BusinessUpdated = 'business.updated';
    case BusinessStatusChanged = 'business.status_changed';
    case BusinessDeleted = 'business.deleted';
    case InvitationCreated = 'invitation.created';
    case InvitationRevoked = 'invitation.revoked';
    case InvitationAccepted = 'invitation.accepted';
    case InvitationDeclined = 'invitation.declined';
    case MemberUpdated = 'member.updated';
    case MemberRemoved = 'member.removed';
}
