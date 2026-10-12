<?php

use App\Identity\Chatter\Actions\CreateChatterAction;
use App\Identity\Chatter\Data\CreateChatterData;
use App\Identity\Workspace\Actions\Account\DeactivateAccountAction;
use App\Identity\Workspace\Actions\Account\ReactivateAccountAction;
use App\Identity\Workspace\Models\User;
use App\Identity\Workspace\Services\AgentSignInTokenService;
use Domain\Agent\Models\Agent;
use Domain\Membership\Team\Services\TeamService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

beforeEach(function (): void {
    $this->owner = User::factory()->create();
    app(TeamService::class)->createDefaultTeam($this->owner);

    $this->agent = Agent::create([
        'workspace_id' => $this->owner->workspace()->id,
        'team_id' => $this->owner->primaryTeam()->id,
        'name' => 'Private Agent',
        'external_id' => Str::random(14),
        'slug' => Str::lower(Str::random(12)),
        'is_public' => false,
    ]);

    // Provisions the chatter + the agent_user grant so the sign-in token resolves.
    $this->chatter = app(CreateChatterAction::class)->execute(new CreateChatterData($this->owner->workspace(), $this->agent, 'Jane'));
    $this->chat = '/chat/'.$this->agent->slug;
});

function signIn(): void
{
    $token = app(AgentSignInTokenService::class)->issue(test()->chatter, test()->agent)['token'];

    test()->get(test()->chat.'?token='.$token)
        ->assertRedirect(test()->chat);
}

it('keeps an active chatter signed in across chat requests', function (): void {
    signIn();
    expect(Auth::guard('chat')->id())->toBe($this->chatter->id);

    // A follow-up chat request is NOT bounced to the login page.
    $response = $this->get($this->chat);
    $location = $response->isRedirect() ? (string) $response->headers->get('Location') : '';
    expect($location)->not->toContain('/login');
});

it('signs a deactivated chatter out of the hosted chat mid-session', function (): void {
    signIn();
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
        ->assertRedirectContains($this->chat.'/login');

    expect(Auth::guard('chat')->check())->toBeFalse();
});

it('blocks a reactivated-then-deactivated chatter again', function (): void {
    signIn();

    app(DeactivateAccountAction::class)->execute($this->chatter, 'temporary');
    Auth::forgetGuards();
    $this->get($this->chat)->assertRedirectContains($this->chat.'/login');

    // Reactivate + sign in again → allowed.
    app(ReactivateAccountAction::class)->execute($this->chatter);
    signIn();
    expect(Auth::guard('chat')->id())->toBe($this->chatter->id);
});

it('tells a signed-out deactivated chatter why they were signed out', function (): void {
    signIn();
    app(DeactivateAccountAction::class)->execute($this->chatter, 'blocked by admin');
    Auth::forgetGuards();

    $this->get($this->chat)
        ->assertRedirectContains($this->chat.'/login')
        ->assertSessionHas('error', 'Your account has been deactivated.');
});
