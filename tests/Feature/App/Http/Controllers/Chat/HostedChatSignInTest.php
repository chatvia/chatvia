<?php

use App\Identity\Chatter\Models\Chatter;
use App\Identity\Chatter\Services\ChatterAuthTokens;
use App\Identity\Workspace\Actions\Account\DeactivateAccountAction;
use App\Identity\Workspace\Actions\Account\UpdatePasswordAction;
use App\Identity\Workspace\Data\Account\UpdatePasswordData;
use App\Identity\Workspace\Models\User;
use App\Identity\Workspace\Services\AgentSignInTokens;
use Domain\Agent\Models\Agent;
use Firebase\JWT\JWT;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

/**
 * Signing in to a protected agent's hosted chat with a link: a chatter's
 * sign-in token (plain or the compact HMAC form) and a staff member's
 * `auth_token` each sign in on the chat's own guard and survive the redirect
 * that strips them from the address. A token signed with the agent's secret
 * never signs in staff. Anyone whose password changes, and a chatter the
 * admin deactivates, is signed out on their next request, not only at their
 * next sign-in.
 */
beforeEach(function (): void {
    $this->agent = Agent::factory()->create(['is_public' => false]);
    $this->chatter = Chatter::factory()->grantedTo($this->agent)->create();
    $this->chat = '/chat/'.$this->agent->slug;
});

/** Sign the chatter in to the hosted chat with a sign-in link. */
function hcsiSignIn(string $issue = 'issue'): void
{
    $token = app(AgentSignInTokens::class)->{$issue}(test()->chatter, test()->agent)['token'];

    test()->get(test()->chat.'?token='.$token)
        ->assertRedirect(test()->chat);
}

it('keeps the chatter signed in across the redirect that strips the token', function (string $issue): void {
    hcsiSignIn($issue);

    $this->get($this->chat)->assertOk();

    expect(Auth::guard('chat')->id())->toBe($this->chatter->id);
})->with([
    'plain token' => 'issue',
    'HMAC token' => 'issueHmac',
]);

it('signs staff in on chat_staff from an auth_token only, and out again once their password changes', function (): void {
    $owner = $this->agent->workspace->owner;
    $forged = JWT::encode([
        'sub' => $owner->external_id,
        'aud' => $this->agent->external_id,
        'exp' => now()->addMinutes(10)->getTimestamp(),
    ], $this->agent->ensureAuthSecret(), 'HS256');

    $this->get("{$this->chat}?token={$forged}")->assertRedirect($this->chat);

    expect(Auth::guard('chat_staff')->check())->toBeFalse()
        ->and(Auth::guard('chat')->check())->toBeFalse();

    $token = app(ChatterAuthTokens::class)->generateToken($this->agent, $owner, 5);

    $this->get("{$this->chat}?auth_token={$token}")->assertRedirect($this->chat);

    expect(Auth::guard('chat_staff')->id())->toBe($owner->id)
        ->and(Auth::guard('chat')->check())->toBeFalse()
        ->and(Auth::guard('web')->check())->toBeFalse();

    $this->get($this->chat)->assertOk();

    app(UpdatePasswordAction::class)->execute(new UpdatePasswordData(User::query()->findOrFail($owner->id), 'brand-new-password-1'));
    Auth::forgetGuards();

    $this->get($this->chat)->assertRedirectContains($this->chat.'/login');

    expect(Auth::guard('chat_staff')->check())->toBeFalse();
});

it('signs a chatter out of the hosted chat mid-session once their password changes or the admin deactivates them', function (): void {
    hcsiSignIn();
    $this->get($this->chat)->assertOk();

    $this->chatter->forceFill(['password' => Hash::make('brand-new-password-1')])->save();
    Auth::forgetGuards();

    $this->get($this->chat)->assertRedirectContains($this->chat.'/login');

    expect(Auth::guard('chat')->check())->toBeFalse();

    hcsiSignIn();
    expect(Auth::guard('chat')->id())->toBe($this->chatter->id);

    app(DeactivateAccountAction::class)->execute($this->chatter, 'blocked by admin');
    Auth::forgetGuards();

    $this->get($this->chat)
        ->assertRedirectContains($this->chat.'/login')
        ->assertSessionHas('error', 'Your account has been deactivated.');

    expect(Auth::guard('chat')->check())->toBeFalse();
});
