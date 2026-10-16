<?php

namespace Domain\Security\Events;

use Domain\Security\Data\SecurityActorData;
use Domain\Security\Enums\SecurityAuditAction;
use Domain\Security\Enums\SecurityAuditOutcome;
use Domain\Security\Listeners\RecordSecurityEvent;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * The single parametric security-audit event. Rather than authoring one class
 * per audited operation, every emission point dispatches this with an `action`
 * discriminator ({@see SecurityAuditAction}) and a fully-denormalized scalar
 * payload, so the queued {@see RecordSecurityEvent} listener can write one
 * immutable `security_audit_logs` row without touching any live request state.
 *
 * `metadata` is a RAW JSON string — the recorder hashes it (sha256) for
 * tamper-evidence BEFORE redacting + size-capping for storage.
 *
 * Build it via {@see self::for()}, which denormalizes the {@see SecurityActorData}.
 */
final readonly class SecurityEventOccurred
{
    use Dispatchable;

    public function __construct(
        public int $workspaceId,
        public string $action,
        public ?int $userId,
        public ?string $userName,
        public ?string $userEmail,
        public ?string $ip,
        public ?string $userAgent,
        public ?string $targetType,
        public ?string $targetId,
        public ?string $targetLabel,
        public string $metadata,
        public string $outcome,
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
        SecurityAuditOutcome $outcome = SecurityAuditOutcome::Success,
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
            outcome: $outcome->value,
        );
    }
}
