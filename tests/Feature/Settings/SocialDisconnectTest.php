<?php

use App\Identity\Workspace\Models\User;
use Support\Enums\User\SocialProvider;

/*
 * Covers SocialController::disconnect — the inline `$user->socials()
 * ->where('provider', …)->delete()` (formerly UnlinkSocialAccountAction). The
 * action was inlined as a trivial single-relation delete; this feature test
 * keeps the behaviour covered end-to-end (route + provider guard + scoped delete).
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
