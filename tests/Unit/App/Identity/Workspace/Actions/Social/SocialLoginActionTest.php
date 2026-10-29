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
it('signs in the linked account, or links a verified email\'s account keeping its password', function (): void {
    $linked = User::factory()->create();
    UserSocial::query()->create(['user_id' => $linked->id, 'provider' => SocialProvider::Google, 'provider_id' => 'g-123']);
    $matched = User::factory()->create(['email' => 'match@example.test', 'password' => 'own-password']);

    $foundLinked = app(SocialLoginAction::class)->execute(new SocialLoginData(provider: SocialProvider::Google, providerId: 'g-123', email: 'someone-else@example.test'));
    $foundMatched = app(SocialLoginAction::class)->execute(new SocialLoginData(provider: SocialProvider::Github, providerId: 'gh-9', email: 'match@example.test', emailVerified: true));

    expect($foundLinked?->is($linked))->toBeTrue()
        ->and($foundMatched?->is($matched))->toBeTrue()
        ->and(UserSocial::query()->where('user_id', $matched->id)->where('provider_id', 'gh-9')->exists())->toBeTrue()
        ->and(Hash::check('own-password', $matched->fresh()->password))->toBeTrue();
});

it('refuses an email the provider says is not verified, and an unknown identity without an email', function (): void {
    User::factory()->create(['email' => 'victim@example.test']);

    $unverified = app(SocialLoginAction::class)->execute(new SocialLoginData(provider: SocialProvider::Github, providerId: 'gh-attacker', email: 'victim@example.test', emailVerified: false));
    $noEmail = app(SocialLoginAction::class)->execute(new SocialLoginData(provider: SocialProvider::Slack, providerId: 'sl-1', email: null));

    expect($unverified)->toBeNull()
        ->and($noEmail)->toBeNull()
        ->and(UserSocial::query()->where('provider_id', 'gh-attacker')->exists())->toBeFalse();
});

it('claims a same-email account that was never verified before linking it', function (): void {
    $user = User::factory()->unverified()->create(['email' => 'early@example.test', 'password' => 'squatter-password']);
    UserSocial::query()->create(['user_id' => $user->id, 'provider' => SocialProvider::Github, 'provider_id' => 'gh-squatter']);

    $found = app(SocialLoginAction::class)->execute(new SocialLoginData(provider: SocialProvider::Google, providerId: 'g-owner', email: 'early@example.test', emailVerified: true));

    $user->refresh();
    expect($found?->is($user))->toBeTrue()
        ->and($user->hasVerifiedEmail())->toBeTrue()
        ->and(Hash::check('squatter-password', $user->password))->toBeFalse()
        ->and($user->socials()->pluck('provider_id')->all())->toBe(['g-owner']);
});
