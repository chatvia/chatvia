<?php

use App\Identity\Chatter\Models\Chatter;
use App\Identity\Workspace\Events\MagicLinkRequested;
use App\Identity\Workspace\Models\User;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;

/**
 * The e-mailed sign-in link is built from the request's host. A forged Host
 * header must not move it to another site (the click would hand the token
 * to whoever runs that site): on an unknown host the link is built on
 * APP_URL.
 */
beforeEach(function (): void {
    Event::fake([MagicLinkRequested::class]);

    $this->owner = User::factory()->create(['email' => 'owner@example.test']);
    $this->agent = createAgent($this->owner, ['slug' => Str::lower(Str::random(12))]);
});

/** The URL of the one magic link that was e-mailed. */
function uauhSentLink(): string
{
    $url = null;

    Event::assertDispatched(MagicLinkRequested::class, function (MagicLinkRequested $event) use (&$url): bool {
        $url = $event->url;

        return true;
    });

    return (string) $url;
}

it('builds the dashboard sign-in link on APP_URL when the Host header is forged', function (): void {
    $this->post('http://evil.example/magic-link', ['email' => 'owner@example.test']);

    expect(uauhSentLink())->toStartWith(Str::rtrim((string) config('app.url'), '/').'/magiclink/')
        ->not->toContain('evil.example');
});

it('builds the hosted chat sign-in link on APP_URL when the Host header is forged', function (): void {
    Chatter::factory()->grantedTo($this->agent)->create(['email' => 'chatter@example.test']);

    $this->post('http://evil.example/chat/'.$this->agent->slug.'/magic-link', ['email' => 'chatter@example.test']);

    expect(uauhSentLink())->toStartWith(Str::rtrim((string) config('app.url'), '/').'/magiclink/')
        ->not->toContain('evil.example');
});

it('builds links on the request host again after a request to an unknown host', function (): void {
    $this->get('http://evil.example/up');

    // APP_URL is http://localhost: an https redirect shows the request's own
    // root was used, not one left forced by the request before.
    $this->get('https://localhost/')
        ->assertRedirect('https://localhost/login');
});
