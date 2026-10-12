<?php

use App\Identity\Chatter\Models\Chatter;
use App\Identity\Workspace\Models\User;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * The hosted-chat password form (/chat/{agent}/login) signs in chatters only.
 * Owners, teammates and admins must pass the dashboard login with its 2FA step,
 * and this form has none, so it must never sign them in.
 */
beforeEach(function (): void {
    $this->owner = User::factory()->create([
        'email' => 'owner@example.test',
        'password' => Hash::make('owner-pass'),
        'two_factor_secret' => encrypt('JBSWY3DPEHPK3PXP'),
        'two_factor_confirmed_at' => now(),
    ]);
    $this->agent = createAgent($this->owner, ['slug' => Str::lower(Str::random(12))]);
    $this->login = "/chat/{$this->agent->slug}/login";
});

it('does not sign in a 2FA-enabled owner through the hosted-chat password form', function (): void {
    $this->from($this->login)
        ->post($this->login, [
            'email' => 'owner@example.test',
            'password' => 'owner-pass',
        ])
        ->assertSessionHasErrors('email');

    expect(Auth::guard('chat')->check())->toBeFalse()
        ->and(Auth::guard('chat_staff')->check())->toBeFalse()
        ->and(Auth::guard('web')->check())->toBeFalse()
        ->and(session('login.id'))->toBeNull();

    // And so the dashboard stays closed.
    $this->get(route('dashboard'))->assertRedirect(route('login'));
});

it('signs a chatter in to the chat only, never the dashboard', function (): void {
    $chatter = Chatter::factory()->grantedTo($this->agent)->create([
        'email' => 'chatter@example.test',
        'password' => Hash::make('chatter-pass'),
    ]);

    $this->post($this->login, [
        'email' => 'chatter@example.test',
        'password' => 'chatter-pass',
    ])->assertSessionHasNoErrors()->assertRedirect("/chat/{$this->agent->slug}");

    expect(Auth::guard('chat')->user()?->is($chatter))->toBeTrue()
        ->and(Auth::guard('chat_staff')->check())->toBeFalse()
        ->and(Auth::guard('web')->check())->toBeFalse();
});

it('answers a correct staff password exactly like a wrong one', function (): void {
    $right = $this->from($this->login)->post($this->login, [
        'email' => 'owner@example.test',
        'password' => 'owner-pass',
    ]);
    $wrong = $this->from($this->login)->post($this->login, [
        'email' => 'owner@example.test',
        'password' => 'not-the-password',
    ]);

    $right->assertSessionHasErrors(['email' => trans('auth.failed')]);
    $wrong->assertSessionHasErrors(['email' => trans('auth.failed')]);
});

it('locks the form after five failures for one workspace and email', function (): void {
    // The route's per-IP throttle would answer 429 first; switch it off to
    // reach the form's own counter.
    $this->withoutMiddleware(ThrottleRequests::class);

    $chatter = Chatter::factory()->grantedTo($this->agent)->create([
        'email' => 'chatter@example.test',
        'password' => Hash::make('chatter-pass'),
    ]);

    foreach (range(1, 5) as $_) {
        $this->from($this->login)->post($this->login, [
            'email' => 'chatter@example.test',
            'password' => 'wrong',
        ])->assertSessionHasErrors(['email' => trans('auth.failed')]);
    }

    // Even the right password is refused while the counter is full.
    $this->from($this->login)->post($this->login, [
        'email' => 'chatter@example.test',
        'password' => 'chatter-pass',
    ])->assertSessionHasErrors('email');

    expect(session('errors')->first('email'))->not->toBe(trans('auth.failed'))
        ->and(Auth::guard('chat')->check())->toBeFalse();
});
