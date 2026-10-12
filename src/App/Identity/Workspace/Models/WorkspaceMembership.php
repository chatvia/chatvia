<?php

declare(strict_types=1);

namespace App\Identity\Workspace\Models;

use App\Identity\Workspace\Enums\WorkspaceRole;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\Unguarded;
use Illuminate\Database\Eloquent\Attributes\WithoutTimestamps;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * A staff account's place in a workspace (`workspace_user`): which workspace
 * and whether they own it. A person belongs to one workspace for now.
 *
 * @property int $workspace_id
 * @property int $user_id
 * @property WorkspaceRole $role
 * @property-read Workspace $workspace
 * @property-read User $user
 */
#[Unguarded]
#[Table(name: 'workspace_user')]
#[WithoutTimestamps]
class WorkspaceMembership extends Pivot
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['role' => WorkspaceRole::class];
    }

    /** @return BelongsTo<Workspace, $this> */
    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
