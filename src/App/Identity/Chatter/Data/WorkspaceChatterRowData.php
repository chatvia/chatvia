<?php

namespace App\Identity\Chatter\Data;

use App\Identity\Workspace\Models\User;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * One chatter in the workspace-level Members page (Settings → Members).
 * Aggregates across every agent the chatter is granted to, so the owner can
 * audit access in one place. `status` is `pending` until the chatter accepts
 * their invite (email_verified_at null) and `active` afterwards.
 *
 * `messages_limit`/`messages_used` reflect the per-chatter monthly cap (null
 * limit = unlimited); `is_deactivated` drives the off-switch badge.
 */
#[TypeScript]
class WorkspaceChatterRowData extends Data
{
    public function __construct(
        public string $external_id,
        public string $name,
        public ?string $email,
        public ?string $avatar,
        public string $status,
        public int $agents_count,
        public string $created_at,
        public ?int $messages_limit,
        public int $messages_used,
        public bool $is_deactivated,
        public ?string $deactivation_reason,
    ) {}

    public static function fromModel(User $chatter): self
    {
        // Eager-load `messageLimit`. The limit carries over to a new month;
        // the count shows 0 until the chatter's first message of that month.
        $limit = $chatter->messageLimit;

        return new self(
            external_id: $chatter->external_id,
            name: $chatter->name,
            email: $chatter->email,
            avatar: $chatter->avatar,
            status: $chatter->email_verified_at !== null ? 'active' : 'pending',
            agents_count: (int) ($chatter->accessible_agents_count ?? $chatter->accessibleAgents()->count()),
            created_at: ($chatter->created_at ?? now())->toIso8601String(),
            messages_limit: $limit?->monthly_limit,
            messages_used: $limit?->usedThisMonth() ?? 0,
            is_deactivated: $chatter->isDeactivated(),
            deactivation_reason: $chatter->deactivation_reason,
        );
    }
}
