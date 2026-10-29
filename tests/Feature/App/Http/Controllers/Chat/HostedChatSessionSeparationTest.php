<?php

use App\Identity\Chatter\Models\Chatter;
use App\Identity\Workspace\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Symfony\Component\HttpFoundation\Cookie;

/**
 * The hosted chat has its own sign-in and session, apart from the dashboard:
 * its own cookie (sent only to /chat, usable inside a third-party iframe) and
 * its own guards (`chat` for chatters, `chat_staff` for staff). Signing in or
 * out on one side never changes the other. Chatters are kept apart per
 * workspace too: the same email in two workspaces is two chatters, each
 * signing in to its own workspace's agents only.
 */
beforeEach(function (): void {
    $this->owner = User::factory()->create();
    $this->agent = createAgent($this->owner, ['slug' => Str::lower(Str::random(12)), 'is_public' => true]);
});

function hcssCookie(TestResponse $response, string $name): ?Cookie
{
    return collect($response->headers->getCookies())->first(fn (Cookie $cookie): bool => $cookie->getName() === $name);
}

it('runs the chat on its own cookie and guard, and never lets a dashboard sign-in into a protected chat', function (): void {
    $chat = $this->get('https://localhost/chat/'.$this->agent->slug);
    $cookie = hcssCookie($chat, config('session.chat.cookie'));

    expect($cookie)->not->toBeNull()
        ->and($cookie?->getPath())->toBe('/chat')
        ->and($cookie?->getSameSite())->toBe('none')
        ->and($cookie?->isSecure())->toBeTrue()
        ->and($cookie?->isPartitioned())->toBeTrue()
        ->and(Auth::getDefaultDriver())->toBe('chat');

    $dashboard = $this->get(route('login'));

    expect(hcssCookie($dashboard, config('session.dashboard.cookie')))->not->toBeNull()
        ->and(hcssCookie($dashboard, config('session.chat.cookie')))->toBeNull()
        ->and(Auth::getDefaultDriver())->toBe('web');

    $this->agent->update(['is_public' => false]);

    $this->actingAs($this->owner)
        ->get('/chat/'.$this->agent->slug)
        ->assertRedirect('/chat/'.$this->agent->slug.'/login');
});

it('keeps the dashboard session when someone signs out of the chat', function (string $guard): void {
    $chatUser = $guard === 'chat' ? Chatter::factory()->grantedTo($this->agent)->create() : $this->owner;

    $this->actingAs($this->owner)
        ->actingAs($chatUser, $guard)
        ->post('/chat/'.$this->agent->slug.'/logout')
        ->assertRedirect();

    expect(Auth::guard($guard)->check())->toBeFalse()
        ->and(Auth::guard('web')->id())->toBe($this->owner->id);
})->with([
    'a chatter' => 'chat',
    'staff' => 'chat_staff',
]);

it('signs the same email in as a separate chatter in each workspace', function (): void {
    $first = createAgent(User::factory()->create(), ['slug' => Str::lower(Str::random(12))]);
    $second = createAgent(User::factory()->create(), ['slug' => Str::lower(Str::random(12))]);
    $firstChatter = Chatter::factory()->grantedTo($first)->create([
        'email' => 'same@example.test',
        'password' => Hash::make('first-pass'),
    ]);
    $secondChatter = Chatter::factory()->grantedTo($second)->create([
        'email' => 'same@example.test',
        'password' => Hash::make('second-pass'),
    ]);

    $this->post("/chat/{$first->slug}/login", ['email' => 'same@example.test', 'password' => 'second-pass'])
        ->assertSessionHasErrors(['email' => trans('auth.failed')]);
    $this->post("/chat/{$second->slug}/login", ['email' => 'same@example.test', 'password' => 'first-pass'])
        ->assertSessionHasErrors(['email' => trans('auth.failed')]);

    expect(Auth::guard('chat')->check())->toBeFalse();

    $this->post("/chat/{$first->slug}/login", ['email' => 'same@example.test', 'password' => 'first-pass'])
        ->assertSessionHasNoErrors()
        ->assertRedirect("/chat/{$first->slug}");

    expect(Auth::guard('chat')->user()?->is($firstChatter))->toBeTrue();

    $this->get("/chat/{$second->slug}")->assertForbidden();

    $this->post("/chat/{$first->slug}/logout")->assertRedirect();

    $this->post("/chat/{$second->slug}/login", ['email' => 'SAME@example.test', 'password' => 'second-pass'])
        ->assertSessionHasNoErrors()
        ->assertRedirect("/chat/{$second->slug}");

    expect(Auth::guard('chat')->user()?->is($secondChatter))->toBeTrue();
});
