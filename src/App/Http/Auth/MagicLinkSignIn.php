<?php

namespace App\Http\Auth;

use App\Identity\Workspace\Actions\Sessions\SendMagicLinkAction;
use App\Identity\Workspace\Models\User;
use Domain\Agent\Models\Agent;
use Illuminate\Http\RedirectResponse;
use MagicLink\Actions\ActionAbstract;

/**
 * What an emailed magic link does when it is opened. This is the magic-link
 * package's action contract (it calls `run()`), not one of our Actions: it is
 * created by {@see SendMagicLinkAction}, stored serialized with the link, and
 * signs the user in through {@see TwoFactorAwareSignIn} so the two-factor
 * challenge still applies. A link sent from a hosted chat carries the agent
 * and signs in to that chat instead of the dashboard.
 */
class MagicLinkSignIn extends ActionAbstract
{
    public function __construct(
        public int $userId,
        public string $redirectUrl,
        public bool $remember = false,
        public ?int $agentId = null,
    ) {}

    public function run(): RedirectResponse
    {
        $user = User::query()->find($this->userId);

        if (! $user instanceof User) {
            return redirect()->route('login');
        }

        if ($this->agentId !== null) {
            $agent = Agent::query()->find($this->agentId);

            return $agent instanceof Agent
                ? app(TwoFactorAwareSignIn::class)->respondForChat($user, $agent)
                : redirect()->route('login');
        }

        return app(TwoFactorAwareSignIn::class)->respond($user, $this->redirectUrl, $this->remember);
    }
}
