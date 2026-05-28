<?php

namespace Domain\Auth\Actions;

use App\Auth\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Support\Enums\CreatedVia;

/**
 * Creates a customer's end-user under their account. External users never log
 * in to the dashboard (random password, no login email) — they only reach an
 * agent's chat via a sign-in link. They are NOT team members and do not spawn
 * a team, so they never count toward seats or appear in member lists.
 */
class CreateExternalUserAction
{
    public function execute(User $owner, string $name, ?string $contactEmail = null): User
    {
        return User::create([
            'name' => $name,
            'email' => null,
            'contact_email' => $contactEmail,
            'password' => Hash::make(Str::random(64)),
            'created_via' => CreatedVia::External,
            'managed_by_user_id' => $owner->id,
            'current_team_id' => $owner->primaryTeam()?->id,
            'email_verified_at' => now(),
        ]);
    }
}
