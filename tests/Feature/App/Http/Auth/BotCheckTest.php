<?php

use App\Http\Auth\BotCheck;
use App\Identity\Workspace\Models\User;
use Domain\Agent\Models\Agent;
use RyanChandler\LaravelCloudflareTurnstile\Facades\Turnstile;

/**
 * The bot check of the public forms (BotCheck): once Cloudflare Turnstile's
 * keys are saved (Settings → Security), the forms switched on there, on the
 * dashboard and in the hosted chat, need a passing Turnstile token, and a
 * form whose switch is off asks for none.
 */

/** Turnstile set up, with the given forms switched on. */
function bctTurnstileOn(bool $signUp = true, bool $signIn = true, bool $passwordReset = true): void
{
    config([
        'services.turnstile.key' => '1x00000000000000000000AA',
        'services.turnstile.secret' => '1x0000000000000000000000000000000AA',
        'chatvia.security.turnstile.sign_up' => $signUp,
        'chatvia.security.turnstile.sign_in' => $signIn,
        'chatvia.security.turnstile.password_reset' => $passwordReset,
    ]);
}

beforeEach(fn () => withLicense());

it('refuses a sign-up without a passing token', function (bool $sendsToken): void {
    bctTurnstileOn();
    $sendsToken ? Turnstile::fake()->fail() : Turnstile::fake();

    $this->from(route('register'))->post(route('register.store'), [
        'name' => 'Bot',
        'email' => 'bot@example.com',
        'password' => 'a-long-Password-123!',
        'password_confirmation' => 'a-long-Password-123!',
        ...($sendsToken ? [BotCheck::FIELD => Turnstile::dummy()] : []),
    ])->assertSessionHasErrors(BotCheck::FIELD);

    expect(User::query()->where('email', 'bot@example.com')->exists())->toBeFalse();
})->with([
    'no token' => false,
    'a failing token' => true,
]);

it('asks the dashboard and hosted chat sign-in forms for a token when they are switched on', function (string $route, array $form, bool $chat): void {
    bctTurnstileOn();
    Turnstile::fake();
    User::factory()->create(['email' => 'ada@example.com']);
    $agent = Agent::factory()->create(['is_public' => false]);

    $this->from(route('login'))
        ->post($chat ? route($route, $agent->slug) : route($route), $form)
        ->assertSessionHasErrors(BotCheck::FIELD);

    expect(auth()->check())->toBeFalse();
})->with([
    'sign-in' => ['login', ['email' => 'ada@example.com', 'password' => 'password'], false],
    'sign-in link' => ['magic-link.send', ['email' => 'ada@example.com'], false],
    'password reset' => ['password.email', ['email' => 'ada@example.com'], false],
    'hosted chat sign-in' => ['chat.login', ['email' => 'ada@example.com', 'password' => 'password'], true],
    'hosted chat sign-in link' => ['chat.magic-link.send', ['email' => 'ada@example.com'], true],
]);

it('lets a passing token through, and asks a form whose switch is off for none', function (): void {
    bctTurnstileOn(signIn: false);
    Turnstile::fake();
    $user = User::factory()->create(['email' => 'ada@example.com']);

    $this->post(route('register.store'), [
        'name' => 'Grace',
        'email' => 'grace@example.com',
        'password' => 'a-long-Password-123!',
        'password_confirmation' => 'a-long-Password-123!',
        BotCheck::FIELD => Turnstile::dummy(),
    ])->assertSessionHasNoErrors();
    auth()->logout();

    $this->post(route('login'), ['email' => 'ada@example.com', 'password' => 'password'])
        ->assertRedirect(route('dashboard'));

    expect(User::query()->where('email', 'grace@example.com')->exists())->toBeTrue()
        ->and(auth()->user()?->is($user))->toBeTrue();
});
