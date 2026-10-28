<?php

use App\Identity\Workspace\Enums\SocialProvider;
use App\Identity\Workspace\Models\User;
use Inertia\Testing\AssertableInertia;

/**
 * The Social accounts page (SocialController): it exists only while the admin
 * has set up at least one sign-in provider (Settings → Sign-in), and
 * disconnecting removes the signed-in person's link to the named provider
 * only.
 */
beforeEach(function (): void {
    foreach (SocialProvider::linkable() as $provider) {
        config(["services.{$provider->value}.client_id" => null, "services.{$provider->value}.client_secret" => null]);
    }

    $this->user = User::factory()->create();
});

it('404s while no sign-in provider is set up, even with an old linked account', function (): void {
    $this->user->socials()->create(['provider' => SocialProvider::Google, 'provider_id' => 'g-1']);

    $this->actingAs($this->user)
        ->get(route('account.social.edit'))
        ->assertNotFound();
});

it('lists the providers that are set up', function (): void {
    config(['services.github.client_id' => 'id', 'services.github.client_secret' => 'secret']);

    $this->actingAs($this->user)
        ->get(route('account.social.edit'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->component('dashboard/account/pages/Social')
            ->where('socialProviders', ['github'])
            ->has('accounts', 1)
            ->where('accounts.0.provider', 'github'));
});

it('disconnects the named provider only', function (): void {
    $this->user->socials()->create(['provider' => SocialProvider::Google, 'provider_id' => 'g-1']);
    $this->user->socials()->create(['provider' => SocialProvider::Github, 'provider_id' => 'gh-1']);

    $this->actingAs($this->user)
        ->delete(route('account.social.disconnect', ['provider' => 'google']))
        ->assertRedirect(route('account.social.edit'))
        ->assertSessionHas('success');

    expect($this->user->socials()->pluck('provider')->all())->toBe([SocialProvider::Github]);
});

it('404s an unknown provider and removes nothing', function (): void {
    $this->user->socials()->create(['provider' => SocialProvider::Google, 'provider_id' => 'g-1']);

    $this->actingAs($this->user)
        ->delete(route('account.social.disconnect', ['provider' => 'bogus']))
        ->assertNotFound();

    expect($this->user->socials()->count())->toBe(1);
});
