<?php

declare(strict_types=1);

namespace App\Identity\Workspace\Actions\Account;

use App\Identity\Workspace\Enums\WorkspaceRole;
use App\Identity\Workspace\Exceptions\WorkspaceLimitReachedException;
use App\Identity\Workspace\Models\User;
use App\Identity\Workspace\Models\Workspace;
use App\Identity\Workspace\Services\WorkspaceAllowance;
use Domain\Membership\Team\Services\TeamService;
use Illuminate\Support\Facades\DB;

/**
 * Gives a new account its own workspace: the workspace row (named after the
 * account), the owner membership and the default team with its roles, which
 * also becomes the account's current team. Sign-up and the admin command both
 * start an account this way. Without a license key the install holds one
 * workspace; a lock keeps two sign-ups at once from both passing the check.
 */
class CreateWorkspaceAction
{
    /** The advisory lock that orders workspace creation. */
    private const int LOCK = 7_301_001;

    public function __construct(
        private readonly TeamService $teams,
        private readonly WorkspaceAllowance $allowance,
    ) {}

    /**
     * @throws WorkspaceLimitReachedException
     */
    public function execute(User $owner): Workspace
    {
        return DB::transaction(function () use ($owner): Workspace {
            DB::select('SELECT pg_advisory_xact_lock(?)', [self::LOCK]);

            if (! $this->allowance->canCreate()) {
                throw WorkspaceLimitReachedException::make();
            }

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
