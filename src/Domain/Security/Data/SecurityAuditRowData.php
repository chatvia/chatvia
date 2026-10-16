<?php

namespace Domain\Security\Data;

use Domain\Security\Models\SecurityAuditLog;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * One row of the security audit viewer (Settings → Security). The live avatar
 * comes from the eager-loaded `user` relation when present; rows without a
 * user (or a deleted user) fall back to the denormalized name and e-mail.
 */
#[TypeScript]
class SecurityAuditRowData extends Data
{
    /**
     * @param  array<string, mixed>|null  $metadata
     */
    public function __construct(
        public int $id,
        public ?string $created_at,
        public string $action,
        public string $action_label,
        public ?string $category,
        public string $outcome,
        public ?int $user_id,
        public ?string $user_name,
        public ?string $user_email,
        public ?string $avatar,
        public ?string $target_type,
        public ?string $target_id,
        public ?string $target_label,
        public ?string $ip,
        public ?string $user_agent,
        public ?array $metadata,
        public bool $integrity_verified,
    ) {}

    public static function fromModel(SecurityAuditLog $row): self
    {
        $action = $row->actionEnum();

        return new self(
            id: $row->id,
            created_at: $row->created_at?->toIso8601String(),
            action: $row->action,
            action_label: $action?->label() ?? $row->action,
            category: $action?->category()->value,
            outcome: $row->outcome,
            user_id: $row->user_id,
            user_name: $row->user_name,
            user_email: $row->user_email,
            avatar: $row->relationLoaded('user') ? $row->user?->avatar : null,
            target_type: $row->target_type,
            target_id: $row->target_id,
            target_label: $row->target_label,
            ip: $row->ip,
            user_agent: $row->user_agent,
            metadata: $row->metadata,
            integrity_verified: $row->metadata_sha256 !== null,
        );
    }
}
