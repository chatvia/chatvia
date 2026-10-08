<?php

namespace App\Identity\Chatter\Actions;

use App\Identity\Chatter\Exceptions\ChatterQuotaExceededException;
use App\Identity\Workspace\Models\User;
use Domain\Agent\Models\Agent;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Support\Enums\User\UserSource;
use Support\Enums\User\UserType;

/**
 * Provisions a customer's chatter (`type=chatter`) under their account and
 * immediately grants them access to one or more agents via the `agent_user`
 * pivot. Without that grant the chatter can't log in
 * ({@see User::canAccessAgent()} blocks them).
 *
 * Login email is optional: pass null for a token-only chatter (random password,
 * no login form), or an email + password for one that can sign in with
 * credentials. Either way the row is `type=chatter`, `source=api`, and is
 * managed by `$owner`.
 */
class CreateChatterAction
{
    /**
     * @param  Agent|Collection<int, Agent>  $agents  one agent, or a collection of
     *                                                (workspace-owned) agents, to grant the chatter access to
     *
     * @throws ChatterQuotaExceededException when the workspace has hit
     *                                       its `workspace_users_limit`.
     */
    public function execute(
        User $owner,
        Agent|Collection $agents,
        string $name,
        ?string $email = null,
        ?string $password = null,
    ): User {
        if (! $owner->canAddWorkspaceUser()) {
            throw ChatterQuotaExceededException::limitReached($owner->quotaLimit('workspace_users_limit'));
        }

        $agentIds = $agents instanceof Agent
            ? [$agents->id]
            : $agents->pluck('id')->all();

        return DB::transaction(function () use ($owner, $agentIds, $name, $email, $password): User {
            $user = User::create([
                'name' => $name,
                'email' => $email,
                'password' => Hash::make($password ?? Str::random(64)),
                'type' => UserType::Chatter,
                'source' => UserSource::Api,
                'workspace_id' => $owner->id,
                'email_verified_at' => now(),
            ]);

            $user->accessibleAgents()->attach($agentIds);

            return $user;
        });
    }
}
