<?php

use App\Identity\Chatter\Models\Chatter;
use App\Identity\Chatter\Services\ChatterAuthTokens;
use App\Identity\Workspace\Services\AgentSignInTokens;
use Domain\Agent\Models\Agent;
use Illuminate\Support\Facades\Auth;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia;

/**
 * A sign-in link opened in a browser already signed in to the chat as someone
 * else does not switch accounts by itself (login CSRF): it waits on a
 * "Continue as …?" page, and only the POST from that page switches. "Stay"
 * keeps the account signed in now.
 */
beforeEach(function (): void {
    $this->agent = Agent::factory()->create(['is_public' => false]);
    $this->current = Chatter::factory()->grantedTo($this->agent)->create(['name' => 'Current Chatter']);
    $this->other = Chatter::factory()->grantedTo($this->agent)->create(['name' => 'Other Chatter']);
    $this->chat = '/chat/'.$this->agent->slug;

    // The browser is signed in to the chat as the current chatter.
    $token = app(AgentSignInTokens::class)->issue($this->current, $this->agent)['token'];
    $this->get("{$this->chat}?token={$token}")->assertRedirect($this->chat);
});

/** Opens a sign-in link for the other chatter. */
function csacOpenLinkForOther(string $param = 'token'): TestResponse
{
    $token = $param === 'token'
        ? app(AgentSignInTokens::class)->issue(test()->other, test()->agent)['token']
        : app(ChatterAuthTokens::class)->generateToken(test()->agent, test()->other, 5);

    return test()->get(test()->chat."?{$param}={$token}");
}

it('asks before a sign-in link switches to another account', function (string $param): void {
    csacOpenLinkForOther($param)->assertRedirect($this->chat.'/switch-account');

    expect(Auth::guard('chat')->id())->toBe($this->current->id);

    $this->get($this->chat.'/switch-account')
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->component('auth/ConfirmChatSwitch')
            ->where('currentName', 'Current Chatter')
            ->where('nextName', 'Other Chatter'));

    expect(Auth::guard('chat')->id())->toBe($this->current->id);
})->with([
    'sign-in link' => 'token',
    'one-time link (magic link, dashboard)' => 'auth_token',
]);

it('switches only once the person confirms', function (): void {
    csacOpenLinkForOther();

    $this->post($this->chat.'/switch-account')->assertRedirect($this->chat);

    expect(Auth::guard('chat')->id())->toBe($this->other->id);
});

it('keeps the current account when the person stays', function (): void {
    csacOpenLinkForOther();

    $this->delete($this->chat.'/switch-account')->assertRedirect($this->chat);
    $this->post($this->chat.'/switch-account')->assertRedirect($this->chat);

    expect(Auth::guard('chat')->id())->toBe($this->current->id);
});

it('re-signs the same account without asking', function (): void {
    $token = app(AgentSignInTokens::class)->issue($this->current, $this->agent)['token'];

    $this->get("{$this->chat}?token={$token}")->assertRedirect($this->chat);

    expect(Auth::guard('chat')->id())->toBe($this->current->id);
});

it('sends the confirm page back to the chat when no link is waiting', function (): void {
    $this->get($this->chat.'/switch-account')->assertRedirect($this->chat);
    $this->post($this->chat.'/switch-account')->assertRedirect($this->chat);

    expect(Auth::guard('chat')->id())->toBe($this->current->id);
});
