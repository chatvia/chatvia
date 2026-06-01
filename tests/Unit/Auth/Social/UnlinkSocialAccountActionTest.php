<?php

use App\Auth\Models\User;
use App\Auth\Models\UserSocial;
use App\Auth\Actions\Social\UnlinkSocialAccountAction;
use Support\Enums\User\SocialProvider;

it('deletes the matching social row for the given provider', function (): void {
    $user = User::factory()->create();
    UserSocial::query()->create([
        'user_id' => $user->id,
        'provider' => SocialProvider::Google,
        'provider_id' => 'pid-1',
    ]);

    app(UnlinkSocialAccountAction::class)->execute($user, SocialProvider::Google);

    expect(UserSocial::query()->where('user_id', $user->id)->where('provider', SocialProvider::Google)->exists())
        ->toBeFalse();
});

it('only removes the targeted provider and leaves the others in place', function (): void {
    $user = User::factory()->create();
    UserSocial::query()->create([
        'user_id' => $user->id,
        'provider' => SocialProvider::Google,
        'provider_id' => 'pid-google',
    ]);
    UserSocial::query()->create([
        'user_id' => $user->id,
        'provider' => SocialProvider::Microsoft,
        'provider_id' => 'pid-microsoft',
    ]);

    app(UnlinkSocialAccountAction::class)->execute($user, SocialProvider::Google);

    expect($user->socials()->where('provider', SocialProvider::Google)->exists())->toBeFalse()
        ->and($user->socials()->where('provider', SocialProvider::Microsoft)->exists())->toBeTrue();
});

it('does not delete other users socials for the same provider', function (): void {
    $alice = User::factory()->create();
    $bob = User::factory()->create();
    UserSocial::query()->create([
        'user_id' => $alice->id,
        'provider' => SocialProvider::Google,
        'provider_id' => 'alice-pid',
    ]);
    UserSocial::query()->create([
        'user_id' => $bob->id,
        'provider' => SocialProvider::Google,
        'provider_id' => 'bob-pid',
    ]);

    app(UnlinkSocialAccountAction::class)->execute($alice, SocialProvider::Google);

    expect($bob->socials()->where('provider', SocialProvider::Google)->exists())->toBeTrue();
});
