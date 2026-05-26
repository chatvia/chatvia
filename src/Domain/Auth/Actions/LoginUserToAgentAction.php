<?php

namespace Domain\Auth\Actions;

use App\Auth\Models\User;
use Domain\Agents\Models\Agent;
use Domain\Auth\Data\LoginData;
use Illuminate\Support\Facades\Auth;

class LoginUserToAgentAction
{
    /**
     * Returns the authenticated user, or null if the credentials don't match
     * or the user has no access to the given agent. Caller decides how to
     * surface failures (rate limit + ValidationException, typically).
     */
    public function execute(LoginData $data, Agent $agent): ?User
    {
        $user = User::where('email', $data->email)->first();

        if (! $user || ! Auth::guard('web')->validate(['email' => $data->email, 'password' => $data->password])) {
            return null;
        }

        if (! $user->canAccessAgent($agent)) {
            return null;
        }

        Auth::guard('web')->login($user, $data->remember);

        $user->update(['last_login_at' => now()]);

        return $user;
    }
}
