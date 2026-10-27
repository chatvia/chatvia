<?php

use App\Identity\Workspace\Actions\Social\SocialLoginAction;
use App\Identity\Workspace\Data\Social\SocialLoginData;
use App\Identity\Workspace\Enums\SocialProvider;
use App\Identity\Workspace\Models\User;
use App\Identity\Workspace\Models\UserSocial;
use Illuminate\Support\Facades\Hash;

/**
 * Which staff account a social sign-in reaches: the one linked to the
 * identity, or the one with the same email when the provider says it is
 * verified (then linked for next time; an unverified one is claimed first so
 * whoever registered it loses their way in). Nothing else.
 */
it('signs in the account already linked to the provider identity', function (): void {
    $user = User::factory()->create();
    UserSocial::query()->create(['user_id' => $user->id, 'provider' => SocialProvider::Google, 'provider_id' => 'g-123']);

    $found = app(SocialLoginAction::class)->execute(new SocialLoginData(provider: SocialProvider::Google, providerId: 'g-123', email: 'someone-else@example.test'));

    expect($found?->is($user))->toBeTrue();
});

it('links a verified email to the matching account so the next sign-in is direct', function (): void {
    $user = User::factory()->create(['email' => 'match@example.test']);

    $found = app(SocialLoginAction::class)->execute(new SocialLoginData(provider: SocialProvider::Github, providerId: 'gh-9', email: 'match@example.test', emailVerified: true));

    expect($found?->is($user))->toBeTrue()
        ->and(UserSocial::query()->where('user_id', $user->id)->where('provider_id', 'gh-9')->exists())->toBeTrue();
});

it('refuses to link an email the provider says is not verified', function (): void {
    User::factory()->create(['email' => 'victim@example.test']);

    expect(app(SocialLoginAction::class)->execute(new SocialLoginData(provider: SocialProvider::Github, providerId: 'gh-attacker', email: 'victim@example.test', emailVerified: false)))->toBeNull()
        ->and(UserSocial::query()->where('provider_id', 'gh-attacker')->exists())->toBeFalse();
});

it('finds no account for an unknown identity without an email', function (): void {
    expect(app(SocialLoginAction::class)->execute(new SocialLoginData(provider: SocialProvider::Slack, providerId: 'sl-1', email: null)))->toBeNull();
});

it('claims a same-email account that was never verified before linking it', function (): void {
    // Someone registered the address first and knows its password: that
    // password, and the social link they added, must stop working.
    $user = User::factory()->unverified()->create(['email' => 'early@example.test', 'password' => 'squatter-password']);
    UserSocial::query()->create(['user_id' => $user->id, 'provider' => SocialProvider::Github, 'provider_id' => 'gh-squatter']);

    $found = app(SocialLoginAction::class)->execute(new SocialLoginData(provider: SocialProvider::Google, providerId: 'g-owner', email: 'early@example.test', emailVerified: true));

    $user->refresh();

    expect($found?->is($user))->toBeTrue()
        ->and($user->hasVerifiedEmail())->toBeTrue()
        ->and(Hash::check('squatter-password', $user->password))->toBeFalse()
        ->and($user->socials()->pluck('provider_id')->all())->toBe(['g-owner']);
});

it('keeps the password of a verified account it links', function (): void {
    $user = User::factory()->create(['email' => 'kept@example.test', 'password' => 'own-password']);

    app(SocialLoginAction::class)->execute(new SocialLoginData(provider: SocialProvider::Google, providerId: 'g-kept', email: 'kept@example.test', emailVerified: true));

    expect(Hash::check('own-password', $user->fresh()->password))->toBeTrue();
});
