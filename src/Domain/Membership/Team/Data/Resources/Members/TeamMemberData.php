<?php

namespace Domain\Membership\Team\Data\Resources\Members;

use App\Identity\Workspace\Models\User;
use Domain\Membership\Team\Models\Role;
use Domain\Membership\Team\Models\Team;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * One row of the team page's member list. The owner has no role pivot row, so
 * a null role renders as the owner role.
 */
#[TypeScript]
class TeamMemberData extends Data
{
    public function __construct(
        public int $id,
        public string $name,
        public string $email,
        public string $role,
        public string $role_name,
        public bool $is_owner,
        public ?string $joined_at,
    ) {}

    /**
     * @param  ?Role  $role  The member's already-resolved team role (null for the
     *                       owner, who holds everything without a pivot row). Pass
     *                       it in — don't resolve per member — so a member list
     *                       issues one roles query instead of one join per row.
     */
    public static function fromUser(User $user, Team $team, ?Role $role): self
    {
        return new self(
            id: $user->id,
            name: $user->name,
            email: $user->email,
            role: $role->code ?? Role::OWNER_CODE,
            role_name: $role->name ?? 'Owner',
            is_owner: $team->user_id === $user->id,
            joined_at: null, // The membership pivot table has no timestamps.
        );
    }
}
