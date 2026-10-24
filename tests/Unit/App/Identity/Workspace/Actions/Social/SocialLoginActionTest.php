<?php

use App\Identity\Workspace\Actions\Social\SocialLoginAction;
use App\Identity\Workspace\Enums\SocialProvider;
use App\Identity\Workspace\Models\User;
use App\Identity\Workspace\Models\UserSocial;

/**
 * Which staff account a social sign-in reaches: the one linked to the
 * identity, or the one with the same email when the provider says it is
 * verified (then linked for next time). Nothing else.
 */
it('signs in the account already linked to the provider identity', function (): void {
    $user = User::factory()->create();
    UserSocial::query()->create(['user_id' => $user->id, 'provider' => SocialProvider::Google, 'provider_id' => 'g-123']);

    $found = app(SocialLoginAction::class)->execute(SocialProvider::Google, 'g-123', 'someone-else@example.test');

    expect($found?->is($user))->toBeTrue();
});

it('links a verified email to the matching account so the next sign-in is direct', function (): void {
    $user = User::factory()->create(['email' => 'match@example.test']);

    $found = app(SocialLoginAction::class)->execute(SocialProvider::Github, 'gh-9', 'match@example.test', emailVerified: true);

    expect($found?->is($user))->toBeTrue()
        ->and(UserSocial::query()->where('user_id', $user->id)->where('provider_id', 'gh-9')->exists())->toBeTrue();
});

it('refuses to link an email the provider says is not verified', function (): void {
    User::factory()->create(['email' => 'victim@example.test']);

    expect(app(SocialLoginAction::class)->execute(SocialProvider::Github, 'gh-attacker', 'victim@example.test', emailVerified: false))->toBeNull()
        ->and(UserSocial::query()->where('provider_id', 'gh-attacker')->exists())->toBeFalse();
});

it('finds no account for an unknown identity without an email', function (): void {
    expect(app(SocialLoginAction::class)->execute(SocialProvider::Slack, 'sl-1', null))->toBeNull();
});
