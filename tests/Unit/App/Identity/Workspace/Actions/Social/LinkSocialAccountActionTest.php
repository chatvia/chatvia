<?php

use App\Identity\Workspace\Actions\Social\LinkSocialAccountAction;
use App\Identity\Workspace\Enums\SocialProvider;
use App\Identity\Workspace\Exceptions\SocialAccountAlreadyLinkedException;
use App\Identity\Workspace\Models\User;
use App\Identity\Workspace\Models\UserSocial;

/**
 * Linking a social sign-in to an account: one row per account and provider,
 * a new provider id replaces the old one, and an identity already linked to
 * another account is refused.
 */
it('keeps one row per account and provider, moving it to a new provider id', function (): void {
    $user = User::factory()->create();
    $action = app(LinkSocialAccountAction::class);

    $first = $action->execute($user, SocialProvider::Google, 'pid-1');
    $again = $action->execute($user, SocialProvider::Google, 'pid-1');
    $moved = $action->execute($user, SocialProvider::Google, 'pid-2');

    expect($first)->toBeInstanceOf(UserSocial::class)
        ->and($first->user_id)->toBe($user->id)
        ->and($first->provider)->toBe(SocialProvider::Google)
        ->and($again->id)->toBe($first->id)
        ->and($moved->id)->toBe($first->id)
        ->and($moved->provider_id)->toBe('pid-2')
        ->and(UserSocial::query()->where('user_id', $user->id)->where('provider', SocialProvider::Google)->count())->toBe(1);
});

it('refuses an identity already linked to another account', function (): void {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $action = app(LinkSocialAccountAction::class);
    $action->execute($owner, SocialProvider::Slack, 'shared-pid');

    expect(fn () => $action->execute($other, SocialProvider::Slack, 'shared-pid'))->toThrow(SocialAccountAlreadyLinkedException::class)
        ->and(UserSocial::query()->where('user_id', $other->id)->exists())->toBeFalse();
});
