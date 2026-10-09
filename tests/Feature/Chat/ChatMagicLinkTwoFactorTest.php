<?php

use App\Identity\Workspace\Events\MagicLinkRequested;
use App\Identity\Workspace\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use PragmaRX\Google2FA\Google2FA;

/**
 * A teammate or owner with two-factor authentication opens a protected agent's
 * hosted chat on the agent's own address and signs in with the emailed link.
 * The link and the two-factor challenge must both work on that address; the
 * redirect that keeps the dashboard off agent addresses must not swallow them.
 */
beforeEach(function (): void {
    $this->secret = new Google2FA()->generateSecretKey();
    $this->owner = User::factory()->create([
        'email' => 'owner-2fa@example.test',
        'two_factor_secret' => encrypt($this->secret),
        'two_factor_confirmed_at' => now(),
    ]);
    // Hosts are lower-cased, so the subdomain must be too.
    $this->agent = createAgent($this->owner, ['sub_domain' => Str::lower(Str::random(12))]);
    $this->host = 'https://'.$this->agent->sub_domain.'.chatvia.test';
});

/**
 * Requests a link on the agent's address and returns the emailed URL.
 */
function requestHostedChatMagicLink(string $host, string $email): string
{
    Event::fake([MagicLinkRequested::class]);

    test()->post($host.'/chat/magic-link', ['email' => $email])->assertSessionHasNoErrors();

    $url = null;
    Event::assertDispatched(MagicLinkRequested::class, function (MagicLinkRequested $event) use (&$url): bool {
        $url = $event->url;

        return true;
    });

    return $url;
}

it('takes a 2FA user from the emailed link through the challenge into the hosted chat', function (): void {
    $link = requestHostedChatMagicLink($this->host, 'owner-2fa@example.test');

    expect($link)->toStartWith($this->host.'/');

    $this->get($link)->assertRedirect($this->host.'/two-factor-challenge');

    expect(Auth::guard('web')->check())->toBeFalse();

    $this->get($this->host.'/two-factor-challenge')->assertOk();

    $this->post($this->host.'/two-factor-challenge', [
        'code' => new Google2FA()->getCurrentOtp($this->secret),
    ])->assertRedirect($this->host.'/chat');

    expect(Auth::guard('web')->id())->toBe($this->owner->id);
});

it('still sends other pages on the agent address to the hosted chat', function (): void {
    $this->get($this->host.'/login')->assertRedirect($this->host.'/chat');
    $this->get($this->host.'/forgot-password')->assertRedirect($this->host.'/chat');
});
