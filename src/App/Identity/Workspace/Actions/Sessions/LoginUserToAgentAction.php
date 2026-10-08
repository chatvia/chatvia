<?php

namespace App\Identity\Workspace\Actions\Sessions;

use App\Identity\Workspace\Data\Sessions\LoginData;
use App\Identity\Workspace\Models\User;
use App\Identity\Workspace\Services\PasswordAuthenticator;
use Domain\Agent\Models\Agent;
use Support\Exceptions\DomainValidationException;

/**
 * Password sign-in on an agent's hosted chat (/chat/login).
 *
 * Only chatters sign in here. Owners, teammates and admins share the `web`
 * guard with the dashboard, and this form has no two-factor step, so signing
 * them in here would skip their 2FA. They open a private agent's chat from the
 * dashboard (which hands over a one-time sign-in token) or use the emailed
 * sign-in link, which runs the 2FA check.
 */
class LoginUserToAgentAction
{
    public function __construct(
        private readonly PasswordAuthenticator $authenticator,
    ) {}

    /**
     * Returns the authenticated user, or null if the credentials don't match,
     * the account is deactivated, or the user has no access to the given
     * agent. Caller decides how to surface failures (rate limit +
     * ValidationException, typically).
     *
     * @throws DomainValidationException when valid credentials belong to a
     *                                   non-chatter account
     */
    public function execute(LoginData $data, Agent $agent): ?User
    {
        $user = $this->authenticator->findUserByCredentials($data->email, $data->password);

        if ($user === null || $user->isDeactivated()) {
            return null;
        }

        if (! $user->isChatter()) {
            throw new DomainValidationException(
                'email',
                __('This form is for chat members only. Team members can open this chat from the dashboard.'),
            );
        }

        if (! $user->canAccessAgent($agent)) {
            return null;
        }

        $this->authenticator->login($user, $data->remember);

        return $user;
    }
}
