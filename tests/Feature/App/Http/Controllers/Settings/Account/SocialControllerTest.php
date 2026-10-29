<?php

use App\Identity\Workspace\Enums\SocialProvider;
use App\Identity\Workspace\Models\User;

/**
 * Disconnecting a social account (SocialController) removes the signed-in
 * person's link to the named provider only.
 */
beforeEach(function (): void {
    foreach (SocialProvider::linkable() as $provider) {
        config(["services.{$provider->value}.client_id" => null, "services.{$provider->value}.client_secret" => null]);
    }

    $this->user = User::factory()->create();
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
