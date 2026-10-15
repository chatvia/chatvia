<?php

use App\Identity\Workspace\Enums\SocialProvider;
use App\Identity\Workspace\Models\User;
use Inertia\Testing\AssertableInertia;

/*
 * Covers SocialController::edit — the Social accounts page exists only while
 * the admin has set up at least one sign-in provider (Settings → Sign-in).
 */

beforeEach(function (): void {
    foreach (SocialProvider::linkable() as $provider) {
        config(["services.{$provider->value}.client_id" => null, "services.{$provider->value}.client_secret" => null]);
    }

    $this->user = User::factory()->create();
});

it('404s when no sign-in provider is set up', function (): void {
    $this->actingAs($this->user)
        ->get(route('account.social.edit'))
        ->assertNotFound();
});

it('404s when no provider is set up even with an old linked account', function (): void {
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
            ->component('account/Social')
            ->where('socialProviders', ['github'])
            ->has('accounts', 1)
            ->where('accounts.0.provider', 'github'));
});
