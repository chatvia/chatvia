<?php

namespace App\Identity\Workspace\Actions\Sessions;

use App\Identity\Workspace\Data\Sessions\LoginData;
use App\Identity\Workspace\Models\User;
use App\Identity\Workspace\Services\PasswordAuthenticator;
use Domain\Agent\Models\Agent;

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

        // A deactivated chatter can't sign in, even with valid credentials.
        if ($user->isDeactivated()) {
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
