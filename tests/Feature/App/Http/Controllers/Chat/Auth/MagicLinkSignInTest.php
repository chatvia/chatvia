<?php

use App\Identity\Chatter\Models\Chatter;
use App\Identity\Workspace\Events\MagicLinkRequested;
use App\Identity\Workspace\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use PragmaRX\Google2FA\Google2FA;

/**
 * Signing in to a protected agent's hosted chat with the emailed link, across
 * the magic-link, two-factor and hand-off routes. The link signs in to the
 * chat's own session, never the dashboard: a chatter on the `chat` guard,
 * staff on `chat_staff`. Staff with two-factor authentication pass the
 * challenge first; the staff-only hand-off route then carries the sign-in
 * into the chat.
 */
beforeEach(function (): void {
    $this->secret = new Google2FA()->generateSecretKey();
    $this->owner = User::factory()->create([
        'email' => 'owner-2fa@example.test',
        'two_factor_secret' => encrypt($this->secret),
        'two_factor_confirmed_at' => now(),
    ]);
    $this->agent = createAgent($this->owner, ['slug' => Str::lower(Str::random(12))]);
    $this->chat = '/chat/'.$this->agent->slug;
});

/**
 * Requests a link from the agent's chat and returns the emailed URL.
 */
function mlsiRequestLink(string $chat, string $email): string
{
    Event::fake([MagicLinkRequested::class]);

    test()->post($chat.'/magic-link', ['email' => $email])->assertSessionHasNoErrors();

    $url = null;
    Event::assertDispatched(MagicLinkRequested::class, function (MagicLinkRequested $event) use (&$url): bool {
        $url = $event->url;

        return true;
    });

    return $url;
}

it('takes a 2FA user from the emailed link through the challenge into the hosted chat', function (): void {
    $link = mlsiRequestLink($this->chat, 'owner-2fa@example.test');

    $this->get($link)->assertRedirect(route('two-factor.login'));

    expect(Auth::guard('web')->check())->toBeFalse()
        ->and(Auth::guard('chat')->check())->toBeFalse();

    $this->post(route('two-factor.login.store'), [
        'code' => new Google2FA()->getCurrentOtp($this->secret),
    ])->assertRedirect('/chat-handoff/'.$this->agent->slug);

    $location = $this->get('/chat-handoff/'.$this->agent->slug)->assertRedirect()->headers->get('Location');

    expect($location)->toContain($this->chat.'?auth_token=');

    $this->get($location)->assertRedirect($this->chat);

    expect(Auth::guard('chat_staff')->id())->toBe($this->owner->id)
        ->and(Auth::guard('chat')->check())->toBeFalse();
});

it('sends a chatter straight into the chat, without a dashboard session', function (): void {
    $chatter = Chatter::factory()->grantedTo($this->agent)->create(['email' => 'chatter@example.test']);

    $link = mlsiRequestLink($this->chat, 'chatter@example.test');

    $location = $this->get($link)->assertRedirect()->headers->get('Location');

    expect($location)->toContain($this->chat.'?auth_token=')
        ->and(Auth::guard('web')->check())->toBeFalse();

    $this->get($location)->assertRedirect($this->chat);

    expect(Auth::guard('chat')->user()?->is($chatter))->toBeTrue()
        ->and(Auth::guard('chat_staff')->check())->toBeFalse();
});
