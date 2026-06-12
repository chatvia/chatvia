<?php

namespace Domain\Security\Events;

use Domain\Security\Data\SecurityActorData;
use Domain\Security\Enums\SecurityAuditAction;
use Domain\Security\Listeners\RecordSecurityEvent;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * The single parametric security-audit event. Rather than authoring one class
 * per audited operation, every emission point dispatches this with an `action`
 * discriminator ({@see SecurityAuditAction}) and a fully-denormalized scalar
 * payload, so the queued {@see RecordSecurityEvent} listener can write one
 * immutable `security_audit_log` row without touching any live request state.
 *
 * `metadata` is a RAW JSON string — the recorder hashes it (sha256) for
 * tamper-evidence BEFORE redacting + size-capping for storage.
 *
 * Build it via {@see self::for()}, which denormalizes the {@see SecurityActorData}.
 */
final class SecurityEventOccurred
{
    use Dispatchable;

    public function __construct(
        public readonly int $workspaceId,
        public readonly string $action,
        public readonly ?int $userId,
        public readonly ?string $userName,
        public readonly ?string $userEmail,
        public readonly ?string $ip,
        public readonly ?string $userAgent,
        public readonly ?string $targetType,
        public readonly ?string $targetId,
        public readonly ?string $targetLabel,
        public readonly string $metadata,
        public readonly string $outcome,
    ) {}

    /**
     * @param  array<string, mixed>  $metadata
     */
    public static function for(
        SecurityAuditAction $action,
        int $workspaceId,
        SecurityActorData $actor,
        ?string $targetType = null,
        int|string|null $targetId = null,
        ?string $targetLabel = null,
        array $metadata = [],
        string $outcome = 'success',
    ): self {
        return new self(
            workspaceId: $workspaceId,
            action: $action->value,
            userId: $actor->id,
            userName: $actor->name,
            userEmail: $actor->email,
            ip: $actor->ip,
            userAgent: $actor->userAgent,
            targetType: $targetType,
            targetId: $targetId === null ? null : (string) $targetId,
            targetLabel: $targetLabel,
            metadata: (string) json_encode($metadata === [] ? (object) [] : $metadata),
            outcome: $outcome,
        );
    }
}
