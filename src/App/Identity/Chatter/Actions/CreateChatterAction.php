<?php

namespace App\Identity\Chatter\Actions;

use App\Identity\Chatter\Data\CreateChatterData;
use App\Identity\Chatter\Exceptions\ChatterQuotaExceededException;
use App\Identity\Workspace\Enums\UserSource;
use App\Identity\Workspace\Enums\UserType;
use App\Identity\Workspace\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Creates a chatter (`type=chatter`, `source=api`) under a workspace and grants
 * it access to one or more of the workspace's agents through the `agent_user`
 * pivot. Without that grant the chatter could not sign in to any agent.
 *
 * The seat check runs inside the transaction with the owner's row locked, so
 * two requests at the same time cannot both take the last seat.
 */
class CreateChatterAction
{
    /**
     * @throws ChatterQuotaExceededException when every seat is taken
     */
    public function execute(CreateChatterData $data): User
    {
        return DB::transaction(function () use ($data): User {
            $owner = $this->lockOwner($data->owner);

            if (! $owner->canAddWorkspaceUser()) {
                throw ChatterQuotaExceededException::limitReached($owner->totalWorkspaceUsersLimit());
            }

            /** @var User $chatter */
            $chatter = User::query()->create([
                'name' => $data->name,
                'email' => $data->email,
                'password' => Hash::make($data->password ?? Str::random(64)),
                'type' => UserType::Chatter,
                'source' => UserSource::Api,
                'workspace_id' => $owner->id,
                'email_verified_at' => now(),
            ]);

            $chatter->accessibleAgents()->attach($data->agentIds());

            return $chatter;
        });
    }

    private function lockOwner(User $owner): User
    {
        /** @var User $locked */
        $locked = User::query()->whereKey($owner->id)->lockForUpdate()->firstOrFail();

        return $locked;
    }
}
