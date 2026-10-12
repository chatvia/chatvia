<?php

declare(strict_types=1);

namespace App\Identity\Workspace\Actions\Account;

use App\Identity\Workspace\Enums\WorkspaceRole;
use App\Identity\Workspace\Models\User;
use App\Identity\Workspace\Models\Workspace;
use Domain\Membership\Team\Services\TeamService;
use Illuminate\Support\Facades\DB;

/**
 * Gives a new account its own workspace: the workspace row (named after the
 * account), the owner membership and the default team with its roles, which
 * also becomes the account's current team. Sign-up and the admin command both
 * start an account this way.
 */
class CreateWorkspaceAction
{
    public function __construct(
        private readonly TeamService $teams,
    ) {}

    public function execute(User $owner): Workspace
    {
        return DB::transaction(function () use ($owner): Workspace {
            /** @var Workspace $workspace */
            $workspace = Workspace::query()->create(['name' => $owner->name]);

            $workspace->members()->attach($owner->id, ['role' => WorkspaceRole::Owner->value]);
            $owner->unsetRelation('workspaceMembership');

            $this->teams->createDefaultTeam($owner);
            $owner->bootstrapCurrentTeam();

            return $workspace;
        });
    }
}
