<?php

namespace Domain\Security\Models;

use App\Identity\Workspace\Models\User;
use Domain\Security\Actions\RecordSecurityEventAction;
use Domain\Security\Enums\SecurityAuditAction;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Immutable, workspace-scoped security audit row (`security_audit_log`). Written
 * once by {@see RecordSecurityEventAction} off the queue; read by the audit
 * viewer (Settings → Governance) and the Compliance API.
 *
 * `user_id` is a nullable FK that nulls on delete, with denormalized
 * `user_name`/`user_email` fallbacks, so a row stays attributable after the
 * actor is removed.
 *
 * @property int $id
 * @property int $workspace_id
 * @property int|null $user_id
 * @property string|null $user_name
 * @property string|null $user_email
 * @property string $action
 * @property string|null $target_type
 * @property string|null $target_id
 * @property string|null $target_label
 * @property string|null $ip
 * @property string|null $user_agent
 * @property array<string, mixed>|null $metadata
 * @property string|null $metadata_sha256
 * @property bool $metadata_truncated
 * @property string $outcome
 * @property Carbon|null $created_at
 * @property-read User|null $user
 */
#[Guarded(['id'])]
#[Table(name: 'security_audit_log')]
class SecurityAuditLog extends Model
{
    /** Insert-only audit log. */
    public const UPDATED_AT = null;

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    #[Scope]
    protected function forWorkspace(Builder $query, int $workspaceId): Builder
    {
        return $query->where('workspace_id', $workspaceId);
    }

    public function actionEnum(): ?SecurityAuditAction
    {
        return SecurityAuditAction::tryFrom($this->action);
    }

    /**
     * Shape one row for the audit viewer. The live avatar is taken from the
     * eager-loaded `user` relation when present; rows without a user (or a
     * deleted user) fall back to the denormalized name.
     *
     * @return array<string, mixed>
     */
    public function toAuditArray(): array
    {
        $action = $this->actionEnum();

        return [
            'id' => $this->id,
            'created_at' => $this->created_at?->toIso8601String(),
            'action' => $this->action,
            'action_label' => $action?->label() ?? $this->action,
            'category' => $action?->category()->value,
            'outcome' => $this->outcome,
            'user_id' => $this->user_id,
            'user_name' => $this->user_name,
            'user_email' => $this->user_email,
            'avatar' => $this->relationLoaded('user') ? $this->user?->avatar : null,
            'target_type' => $this->target_type,
            'target_id' => $this->target_id,
            'target_label' => $this->target_label,
            'ip' => $this->ip,
            'user_agent' => $this->user_agent,
            'metadata' => $this->metadata,
            'integrity_verified' => $this->metadata_sha256 !== null,
        ];
    }

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'metadata_truncated' => 'boolean',
            'created_at' => 'datetime',
        ];
    }
}
