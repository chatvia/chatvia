<?php

use App\Http\Auth\BotCheck;
use App\Identity\Workspace\Models\User;
use Domain\Agent\Models\Agent;
use Inertia\Testing\AssertableInertia;
use RyanChandler\LaravelCloudflareTurnstile\Facades\Turnstile;

/**
 * The bot check of the public forms (BotCheck): once Cloudflare Turnstile's
 * keys are saved (Settings → Security), the forms switched on there need a
 * passing Turnstile token, and the pages get the site key to render the
 * widget. Without keys, no form asks for one.
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

it('asks no form for a token while Turnstile is not set up', function (): void {
    config(['services.turnstile.key' => null, 'services.turnstile.secret' => null, 'chatvia.security.turnstile.sign_up' => true]);

    $this->post(route('register.store'), [
        'name' => 'Ada',
        'email' => 'ada@example.com',
        'password' => 'a-long-Password-123!',
        'password_confirmation' => 'a-long-Password-123!',
    ])->assertSessionHasNoErrors();

    expect(User::query()->where('email', 'ada@example.com')->exists())->toBeTrue()
        ->and(app(BotCheck::class)->forPages())->toBeNull();
});

it('refuses a sign-up without a passing token', function (bool $sendsToken): void {
    bctTurnstileOn();
    $sendsToken ? Turnstile::fake()->fail() : Turnstile::fake();

    $this->from(route('register'))->post(route('register.store'), [
        'name' => 'Bot',
        'email' => 'bot@example.com',
        'password' => 'a-long-Password-123!',
        'password_confirmation' => 'a-long-Password-123!',
        ...($sendsToken ? [BotCheck::FIELD => Turnstile::dummy()] : []),
    ])->assertSessionHasErrors($sendsToken ? BotCheck::FIELD : [BotCheck::FIELD => 'Complete the security check.']);

    expect(User::query()->where('email', 'bot@example.com')->exists())->toBeFalse();
})->with([
    'no token' => false,
    'a failing token' => true,
]);

it('lets a sign-up with a passing token through', function (): void {
    bctTurnstileOn();
    Turnstile::fake();

    $this->post(route('register.store'), [
        'name' => 'Ada',
        'email' => 'ada@example.com',
        'password' => 'a-long-Password-123!',
        'password_confirmation' => 'a-long-Password-123!',
        BotCheck::FIELD => Turnstile::dummy(),
    ])->assertSessionHasNoErrors();

    expect(User::query()->where('email', 'ada@example.com')->exists())->toBeTrue();
});

it('asks the dashboard sign-in forms for a token when they are switched on', function (string $route, array $form): void {
    bctTurnstileOn();
    Turnstile::fake();
    User::factory()->create(['email' => 'ada@example.com']);

    $this->from(route('login'))
        ->post(route($route), $form)
        ->assertSessionHasErrors(BotCheck::FIELD);

    expect(auth()->check())->toBeFalse();
})->with([
    'sign-in' => ['login', ['email' => 'ada@example.com', 'password' => 'password']],
    'sign-in link' => ['magic-link.send', ['email' => 'ada@example.com']],
    'password reset' => ['password.email', ['email' => 'ada@example.com']],
]);

it('signs in with a passing token', function (): void {
    bctTurnstileOn();
    Turnstile::fake();
    User::factory()->create(['email' => 'ada@example.com']);

    $this->post(route('login'), ['email' => 'ada@example.com', 'password' => 'password', BotCheck::FIELD => Turnstile::dummy()])
        ->assertRedirect(route('dashboard'));

    expect(auth()->check())->toBeTrue();
});

it('leaves a form alone when its switch is off', function (): void {
    bctTurnstileOn(signIn: false);
    $user = User::factory()->create(['email' => 'ada@example.com']);

    $this->post(route('login'), ['email' => 'ada@example.com', 'password' => 'password'])
        ->assertRedirect(route('dashboard'));
    expect(auth()->user()?->is($user))->toBeTrue();
});

it('gives the pages the site key and the forms it protects', function (): void {
    bctTurnstileOn(signIn: false);

    $this->get(route('login'))->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
        ->where('turnstile.site_key', '1x00000000000000000000AA')
        ->where('turnstile.forms', ['sign_up', 'password_reset']));
});

it('checks the hosted chat sign-in forms too, and gives the chat sign-in page the site key', function (): void {
    bctTurnstileOn();
    Turnstile::fake();
    $agent = Agent::factory()->create(['is_public' => false]);

    $this->get(route('chat.login.show', $agent->slug))->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
        ->component('auth/LoginChatter')
        ->where('turnstile.site_key', '1x00000000000000000000AA'));

    $this->post(route('chat.login', $agent->slug), ['email' => 'ada@example.com', 'password' => 'password'])
        ->assertSessionHasErrors(BotCheck::FIELD);
    $this->post(route('chat.magic-link.send', $agent->slug), ['email' => 'ada@example.com'])
        ->assertSessionHasErrors(BotCheck::FIELD);
    $this->post(route('chat.magic-link.send', $agent->slug), ['email' => 'ada@example.com', BotCheck::FIELD => Turnstile::dummy()])
        ->assertSessionHasNoErrors();
});
