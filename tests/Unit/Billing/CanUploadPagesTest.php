<?php

use App\Identity\Workspace\Models\User;
use Domain\Billing\Models\UserQuota;

/**
 * Registration no longer auto-provisions a user_quotas row (the trial-checkout
 * webhook does), so each test seeds one explicitly with a 100-page limit.
 */
function userWithPagesQuota(int $pagesLimit = 100): User
{
    $user = User::factory()->create();

    UserQuota::firstOrCreate(
        ['user_id' => $user->id],
        planFixedLimits(),
    )->update(['pages_limit' => $pagesLimit]);

    return $user;
}

it('allows upload when remaining quota exceeds requested amount', function (): void {
    $user = userWithPagesQuota(100);

    expect($user->consumption()->canUploadPages(50))->toBeTrue();
});

it('allows upload at the exact boundary (remaining equals requested)', function (): void {
    $user = userWithPagesQuota(100);

    expect($user->consumption()->canUploadPages(100))->toBeTrue();
});

it('blocks upload when requested amount exceeds remaining quota', function (): void {
    $user = userWithPagesQuota(100);

    expect($user->consumption()->canUploadPages(101))->toBeFalse();
});
