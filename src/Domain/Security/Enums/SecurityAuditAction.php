<?php

declare(strict_types=1);

namespace Domain\Security\Enums;

use Domain\Security\Events\SecurityEventOccurred;
use Domain\Security\Models\SecurityAuditLog;

/**
 * The discriminator for every {@see SecurityAuditLog}
 * row. One parametric event ({@see SecurityEventOccurred})
 * carries an `action` rather than authoring a class per audited operation.
 *
 * `category()` groups actions for the audit-viewer filters; `label()` is the
 * human-readable description shown in the viewer and exported reports.
 */
enum SecurityAuditAction: string
{
    case AuthLogin = 'auth.login';
    case AuthLoginFailed = 'auth.login_failed';
    case AuthLogout = 'auth.logout';

    case MemberAdded = 'member.added';
    case MemberRemoved = 'member.removed';
    case MemberRoleChanged = 'member.role_changed';
    case RoleCreated = 'role.created';
    case RoleUpdated = 'role.updated';
    case RoleDeleted = 'role.deleted';

    case AgentSettingsChanged = 'agent.settings_changed';
    case ApiKeyCreated = 'api_key.created';
    case ApiKeyDeleted = 'api_key.deleted';
    case ConnectorAttached = 'connector.attached';
    case ConnectorDetached = 'connector.detached';

    case PolicyChanged = 'policy.changed';
    case PolicyIpAllowlistChanged = 'policy.ip_allowlist_changed';
    case PolicyRetentionChanged = 'policy.retention_changed';
    case PolicyWebhookDisabled = 'policy.webhook_disabled';

    case DataExported = 'data.exported';
    case DataDeletionRequested = 'data.deletion_requested';
    case AuditExported = 'audit.exported';

    case ScimUserProvisioned = 'scim.user.provisioned';
    case ScimUserDeprovisioned = 'scim.user.deprovisioned';
    case ScimGroupChanged = 'scim.group.changed';

    case GuardrailAttackBlocked = 'guardrail.attack_blocked';
    case VisitorBlocked = 'visitor.blocked';
    case VisitorUnblocked = 'visitor.unblocked';
    case ChatterBlocked = 'chatter.blocked';

    public function label(): string
    {
        return match ($this) {
            self::AuthLogin => 'Signed in',
            self::AuthLoginFailed => 'Failed sign-in',
            self::AuthLogout => 'Signed out',
            self::MemberAdded => 'Member added',
            self::MemberRemoved => 'Member removed',
            self::MemberRoleChanged => 'Member role changed',
            self::RoleCreated => 'Role created',
            self::RoleUpdated => 'Role updated',
            self::RoleDeleted => 'Role deleted',
            self::AgentSettingsChanged => 'Agent settings changed',
            self::ApiKeyCreated => 'API token created',
            self::ApiKeyDeleted => 'API token revoked',
            self::ConnectorAttached => 'Connector attached',
            self::ConnectorDetached => 'Connector detached',
            self::PolicyChanged => 'Security policy changed',
            self::PolicyIpAllowlistChanged => 'IP allowlist changed',
            self::PolicyRetentionChanged => 'Data retention changed',
            self::PolicyWebhookDisabled => 'Audit webhook auto-disabled',
            self::DataExported => 'Data exported',
            self::DataDeletionRequested => 'Data deletion requested',
            self::AuditExported => 'Audit log exported',
            self::ScimUserProvisioned => 'User provisioned (SCIM)',
            self::ScimUserDeprovisioned => 'User deprovisioned (SCIM)',
            self::ScimGroupChanged => 'Group changed (SCIM)',
            self::GuardrailAttackBlocked => 'Attack blocked by guardrails',
            self::VisitorBlocked => 'Visitor blocked',
            self::VisitorUnblocked => 'Visitor unblocked',
            self::ChatterBlocked => 'Chatter blocked',
        };
    }

    public function category(): SecurityAuditCategory
    {
        return match ($this) {
            self::AuthLogin,
            self::AuthLoginFailed,
            self::AuthLogout => SecurityAuditCategory::Auth,

            self::MemberAdded,
            self::MemberRemoved,
            self::MemberRoleChanged,
            self::RoleCreated,
            self::RoleUpdated,
            self::RoleDeleted,
            self::ScimUserProvisioned,
            self::ScimUserDeprovisioned,
            self::ScimGroupChanged => SecurityAuditCategory::Membership,

            self::AgentSettingsChanged,
            self::ApiKeyCreated,
            self::ApiKeyDeleted,
            self::ConnectorAttached,
            self::ConnectorDetached,
            self::PolicyChanged,
            self::PolicyIpAllowlistChanged,
            self::PolicyRetentionChanged,
            self::PolicyWebhookDisabled => SecurityAuditCategory::Config,

            self::DataExported,
            self::DataDeletionRequested,
            self::AuditExported => SecurityAuditCategory::Data,

            self::GuardrailAttackBlocked,
            self::VisitorBlocked,
            self::VisitorUnblocked,
            self::ChatterBlocked => SecurityAuditCategory::Threat,
        };
    }
}
