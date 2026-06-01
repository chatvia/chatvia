<?php

namespace App\Auth\Actions\Chatters;

use App\Auth\Exceptions\ChatterQuotaExceededException;
use App\Auth\Models\User;
use Domain\Agents\Models\Agent;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Support\Enums\User\UserSource;
use Support\Enums\User\UserType;

/**
 * Provisions a customer's chatter (`type=chatter`) under their account and
 * immediately grants them access to a single agent via the `agent_user` pivot.
 * Without that grant the chatter can't log in ({@see User::canAccessAgent()}
 * blocks them).
 *
 * Login email is optional: pass null for a token-only chatter (random password,
 * no login form), or an email + password for one that can sign in with
 * credentials. Either way the row is `type=chatter`, `source=api`, and is
 * managed by `$owner`.
 */
class CreateChatterAction
{
    /**
     * @throws ChatterQuotaExceededException when the workspace has hit
     *                                       its `members_limit` of chatters.
     */
    public function execute(
        User $owner,
        Agent $agent,
        string $name,
        ?string $email = null,
        ?string $password = null,
    ): User {
        if (! $owner->canAddChatter()) {
            throw ChatterQuotaExceededException::limitReached($owner->quota->members_limit);
        }

        return DB::transaction(function () use ($owner, $agent, $name, $email, $password): User {
            $user = User::create([
                'name' => $name,
                'email' => $email,
                'password' => Hash::make($password ?? Str::random(64)),
                'type' => UserType::Chatter,
                'source' => UserSource::Api,
                'workspace_id' => $owner->id,
                'email_verified_at' => now(),
            ]);

            $user->accessibleAgents()->attach($agent->id);

            return $user;
        });
    }
}
