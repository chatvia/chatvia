<?php

use App\Identity\Chatter\Models\Chatter;
use App\Identity\Workspace\Actions\Account\DeactivateAccountAction;
use App\Identity\Workspace\Actions\Account\ReactivateAccountAction;
use App\Identity\Workspace\Services\AgentSignInTokenService;
use Domain\Agent\Models\Agent;
use Illuminate\Support\Facades\Auth;

/**
 * A chatter the admin deactivates is signed out of the hosted chat on their
 * next request, not only at their next sign-in.
 */
beforeEach(function (): void {
    $this->agent = Agent::factory()->create(['is_public' => false]);

    // A chatter granted the agent, so the sign-in token resolves.
    $this->chatter = Chatter::factory()->grantedTo($this->agent)->create();
    $this->chat = '/chat/'.$this->agent->slug;
});

/** Sign the chatter in to the hosted chat with a sign-in link. */
function cdSignIn(): void
{
    $token = app(AgentSignInTokenService::class)->issue(test()->chatter, test()->agent)['token'];

    test()->get(test()->chat.'?token='.$token)
        ->assertRedirect(test()->chat);
}

it('signs a deactivated chatter out of the hosted chat mid-session and says why', function (): void {
    cdSignIn();
    expect(Auth::guard('chat')->id())->toBe($this->chatter->id);

    // Admin deactivates the chatter while their session is live.
    app(DeactivateAccountAction::class)->execute($this->chatter, 'blocked by admin');

    // In production each request is a fresh process that re-loads the user from
    // the DB; the test process caches the guard user, so forget it to simulate
    // that fresh load (otherwise the stale, still-active instance is reused).
    Auth::forgetGuards();

    // The very next chat request must bounce them to login (login-time gates
    // can't catch an already-open session — this is the per-request re-check).
    $this->get($this->chat)
        ->assertRedirectContains($this->chat.'/login')
        ->assertSessionHas('error', 'Your account has been deactivated.');

    expect(Auth::guard('chat')->check())->toBeFalse();
});

it('lets a chatter back in once reactivated', function (): void {
    cdSignIn();

    app(DeactivateAccountAction::class)->execute($this->chatter, 'temporary');
    Auth::forgetGuards();
    $this->get($this->chat)->assertRedirectContains($this->chat.'/login');

    // Reactivate + sign in again → allowed.
    app(ReactivateAccountAction::class)->execute($this->chatter);
    cdSignIn();
    expect(Auth::guard('chat')->id())->toBe($this->chatter->id);
});
