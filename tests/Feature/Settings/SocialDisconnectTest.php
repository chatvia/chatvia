<?php

use App\Identity\Workspace\Enums\SocialProvider;
use App\Identity\Workspace\Models\User;

/*
 * Covers SocialController::destroy, a single-relation delete
 * (`$user->socials()->where('provider', …)->delete()`): the route, the
 * provider guard and the delete scoped to the signed-in user.
 */

beforeEach(function (): void {
    $this->user = User::factory()->create();
});

it('disconnects a linked social provider', function (): void {
    $this->user->socials()->create([
        'provider' => SocialProvider::Google,
        'provider_id' => 'google-123',
    ]);

    $this->actingAs($this->user)
        ->delete(route('account.social.disconnect', ['provider' => 'google']))
        ->assertRedirect(route('account.social.edit'))
        ->assertSessionHas('success');

    expect($this->user->socials()->count())->toBe(0);
});

it('only disconnects the named provider', function (): void {
    $this->user->socials()->create(['provider' => SocialProvider::Google, 'provider_id' => 'g-1']);
    $this->user->socials()->create(['provider' => SocialProvider::Github, 'provider_id' => 'gh-1']);

    $this->actingAs($this->user)
        ->delete(route('account.social.disconnect', ['provider' => 'google']));

    expect($this->user->socials()->pluck('provider')->all())->toBe([SocialProvider::Github]);
});

it('404s an unknown provider and removes nothing', function (): void {
    $this->user->socials()->create(['provider' => SocialProvider::Google, 'provider_id' => 'g-1']);

    $this->actingAs($this->user)
        ->delete(route('account.social.disconnect', ['provider' => 'bogus']))
        ->assertNotFound();

    expect($this->user->socials()->count())->toBe(1);
});
