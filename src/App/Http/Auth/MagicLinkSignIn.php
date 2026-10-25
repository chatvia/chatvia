<?php

namespace App\Http\Auth;

use App\Identity\Chatter\Models\Chatter;
use App\Identity\Workspace\Actions\Account\ClaimUnverifiedAccountAction;
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
 * and signs in to that chat instead of the dashboard; it names either a staff
 * account (`userId`) or a chatter (`chatterId`), whose ids come from different
 * tables.
 *
 * Opening the link proves the email, so a staff account that was never
 * verified is claimed first ({@see ClaimUnverifiedAccountAction}): someone
 * who registered the address before its owner loses the password and any
 * sign-in they set up.
 */
class MagicLinkSignIn extends ActionAbstract
{
    public function __construct(
        public ?int $userId,
        public string $redirectUrl,
        public bool $remember = false,
        public ?int $agentId = null,
        public ?int $chatterId = null,
    ) {}

    public function run(): RedirectResponse
    {
        if ($this->chatterId !== null) {
            return $this->signInChatter();
        }

        $user = $this->userId !== null ? User::query()->find($this->userId) : null;

        if (! $user instanceof User) {
            return redirect()->route('login');
        }

        if (! $user->isDeactivated()) {
            app(ClaimUnverifiedAccountAction::class)->execute($user);
        }

        if ($this->agentId !== null) {
            $agent = Agent::query()->find($this->agentId);

            return $agent instanceof Agent
                ? app(TwoFactorAwareSignIn::class)->respondForChat($user, $agent)
                : redirect()->route('login');
        }

        return app(TwoFactorAwareSignIn::class)->respond($user, $this->redirectUrl, $this->remember);
    }

    /** A chatter's link always names their agent's chat; chatters have no dashboard. */
    private function signInChatter(): RedirectResponse
    {
        $chatter = Chatter::query()->find($this->chatterId);
        $agent = $this->agentId !== null ? Agent::query()->find($this->agentId) : null;

        if (! $chatter instanceof Chatter || ! $agent instanceof Agent) {
            return redirect()->route('login');
        }

        return app(TwoFactorAwareSignIn::class)->respondForChatter($chatter, $agent);
    }
}
