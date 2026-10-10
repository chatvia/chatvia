<?php

use App\Identity\Workspace\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Symfony\Component\HttpFoundation\Cookie;

/**
 * The hosted chat has its own sign-in and session, apart from the dashboard:
 * its own cookie (sent only to /chat) and its own `chat` guard. Signing in or
 * out on one side never changes the other.
 */
beforeEach(function (): void {
    $this->owner = User::factory()->create();
    $this->agent = createAgent($this->owner, ['slug' => Str::lower(Str::random(12)), 'is_public' => true]);
});

function sessionCookieOf(TestResponse $response, string $name): ?Cookie
{
    return collect($response->headers->getCookies())->first(fn (Cookie $cookie): bool => $cookie->getName() === $name);
}

it('runs a chat request on the chat session cookie and guard', function (): void {
    $response = $this->get('/chat/'.$this->agent->slug);

    $cookie = sessionCookieOf($response, config('session.chat.cookie'));

    expect($cookie)->not->toBeNull()
        ->and($cookie?->getPath())->toBe('/chat')
        ->and(Auth::getDefaultDriver())->toBe('chat');
});

it('runs every other request on the dashboard cookie and guard, also after a chat request', function (): void {
    $this->get('/chat/'.$this->agent->slug);

    $response = $this->get(route('login'));

    expect(sessionCookieOf($response, config('session.dashboard.cookie')))->not->toBeNull()
        ->and(sessionCookieOf($response, config('session.chat.cookie')))->toBeNull()
        ->and(Auth::getDefaultDriver())->toBe('web');
});

it('makes the chat cookie usable inside a third-party iframe over HTTPS', function (): void {
    $cookie = sessionCookieOf($this->get('https://localhost/chat/'.$this->agent->slug), config('session.chat.cookie'));

    expect($cookie?->getSameSite())->toBe('none')
        ->and($cookie?->isSecure())->toBeTrue()
        ->and($cookie?->isPartitioned())->toBeTrue();
});

it('does not carry a dashboard sign-in into a protected chat', function (): void {
    $this->agent->update(['is_public' => false]);

    $this->actingAs($this->owner)
        ->get('/chat/'.$this->agent->slug)
        ->assertRedirect('/chat/'.$this->agent->slug.'/login');
});

it('keeps the dashboard session when the visitor signs out of the chat', function (): void {
    $chatter = User::factory()->chatter($this->owner)->create();
    $chatter->accessibleAgents()->attach($this->agent);

    $this->actingAs($this->owner)
        ->actingAs($chatter, 'chat')
        ->post('/chat/'.$this->agent->slug.'/logout')
        ->assertRedirect();

    expect(Auth::guard('chat')->check())->toBeFalse()
        ->and(Auth::guard('web')->id())->toBe($this->owner->id);
});
