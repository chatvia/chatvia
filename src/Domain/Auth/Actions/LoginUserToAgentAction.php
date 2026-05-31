<?php

namespace Domain\Auth\Actions;

use App\Auth\Models\User;
use Domain\Agents\Models\Agent;
use Domain\Auth\Data\LoginData;
use Domain\Auth\Services\PasswordAuthenticator;

class LoginUserToAgentAction
{
    public function __construct(
        private readonly PasswordAuthenticator $authenticator,
    ) {}

    /**
     * Returns the authenticated user, or null if the credentials don't match
     * or the user has no access to the given agent. Caller decides how to
     * surface failures (rate limit + ValidationException, typically).
     */
    public function execute(LoginData $data, Agent $agent): ?User
    {
        $user = User::where('email', $data->email)->first();

        if (! $user || ! $this->authenticator->validateCredentials($data->email, $data->password)) {
            return null;
        }

        if (! $user->canAccessAgent($agent)) {
            return null;
        }

        $this->authenticator->login($user, $data->remember);

        $user->update(['last_login_at' => now()]);

        return $user;
    }
}
