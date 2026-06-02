<?php

use App\Workspace\Models\User;
use App\Workspace\Models\UserSocial;
use App\Workspace\Actions\Social\LinkSocialAccountAction;
use App\Workspace\Exceptions\SocialAccountAlreadyLinkedException;
use Support\Enums\User\SocialProvider;

it('creates a UserSocial row for a fresh link', function (): void {
    $user = User::factory()->create();

    $social = app(LinkSocialAccountAction::class)->execute(
        $user,
        SocialProvider::Google,
        'google-provider-id-123',
    );

    expect($social)->toBeInstanceOf(UserSocial::class)
        ->and($social->user_id)->toBe($user->id)
        ->and($social->provider)->toBe(SocialProvider::Google)
        ->and($social->provider_id)->toBe('google-provider-id-123');
});

it('is idempotent for the same (user, provider, provider_id)', function (): void {
    $user = User::factory()->create();
    $action = app(LinkSocialAccountAction::class);

    $first = $action->execute($user, SocialProvider::Google, 'pid-1');
    $second = $action->execute($user, SocialProvider::Google, 'pid-1');

    expect($second->id)->toBe($first->id)
        ->and(UserSocial::query()->where('user_id', $user->id)->where('provider', SocialProvider::Google)->count())->toBe(1);
});

it('updates provider_id when the same user re-links the same provider with a different id', function (): void {
    $user = User::factory()->create();
    $action = app(LinkSocialAccountAction::class);

    $first = $action->execute($user, SocialProvider::Microsoft, 'old-id');
    $second = $action->execute($user, SocialProvider::Microsoft, 'new-id');

    expect($second->id)->toBe($first->id)
        ->and($second->provider_id)->toBe('new-id');
});

it('throws when the provider identity is already linked to a different user', function (): void {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $action = app(LinkSocialAccountAction::class);

    $action->execute($owner, SocialProvider::Slack, 'shared-pid');

    $action->execute($other, SocialProvider::Slack, 'shared-pid');
})->throws(SocialAccountAlreadyLinkedException::class);
