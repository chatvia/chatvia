<?php

namespace Domain\Auth\Data;

use App\Auth\Models\User;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * One chatter in the workspace-level Members page (Settings → Members).
 * Aggregates across every agent the chatter is granted to, so the owner can
 * audit access in one place. `status` is `pending` until the chatter accepts
 * their invite (email_verified_at null) and `active` afterwards.
 */
#[TypeScript]
class WorkspaceChatterRowData extends Data
{
    public function __construct(
        public string $external_id,
        public string $name,
        public ?string $email,
        public string $status,
        public int $agents_count,
        public string $created_at,
    ) {}

    public static function fromModel(User $chatter): self
    {
        return new self(
            external_id: $chatter->external_id,
            name: $chatter->name,
            email: $chatter->email,
            status: $chatter->email_verified_at !== null ? 'active' : 'pending',
            agents_count: (int) ($chatter->accessible_agents_count ?? $chatter->accessibleAgents()->count()),
            created_at: ($chatter->created_at ?? now())->toIso8601String(),
        );
    }
}
