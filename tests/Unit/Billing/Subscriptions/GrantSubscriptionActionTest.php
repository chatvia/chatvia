<?php

use App\Identity\Workspace\Models\User;
use Domain\Billing\Actions\Subscriptions\GrantSubscriptionAction;
use Illuminate\Support\Facades\Cache;

beforeEach(function (): void {
    Cache::flush();

    // A controlled package so the test does not depend on real Stripe prices.
    config([
        'billing.packages.test_pkg.quota_limits.monthly' => ['messages_limit' => 5000],
        'billing.packages.test_pkg.quota_limits.yearly' => ['messages_limit' => 60000],
        'billing.packages.test_pkg.quota_limits.fixed' => [
            'agents_limit' => 3,
            'pages_limit' => 1000,
            'minutes_limit' => 60,
            'custom_domains_limit' => 0,
            'workspace_users_limit' => 1,
            'links_limit' => 50,
            'teams_limit' => 1,
        ],
    ]);
});

it('grants the package quota, plan label and message credits', function (): void {
    $user = User::factory()->workspace()->create();

    app(GrantSubscriptionAction::class)->execute($user, 'test_pkg', isYearly: false);

    $user->refresh();

    expect($user->accountBilling()->plan)->toBe('test_pkg')
        ->and((int) $user->quota->agents_limit)->toBe(3)
        ->and((int) $user->quota->pages_limit)->toBe(1000)
        ->and((int) $user->quota->teams_limit)->toBe(1)
        ->and((int) $user->credit->messages)->toBe(5000);
});

it('uses the yearly credit allotment and a one-year expiry for yearly grants', function (): void {
    $user = User::factory()->workspace()->create();

    app(GrantSubscriptionAction::class)->execute($user, 'test_pkg', isYearly: true);

    $user->refresh();

    expect((int) $user->credit->messages)->toBe(60000)
        ->and($user->credit->ends_at->isAfter(now()->addMonths(6)))->toBeTrue();
});

it('does nothing for an unknown package', function (): void {
    $user = User::factory()->workspace()->create();

    app(GrantSubscriptionAction::class)->execute($user, 'no-such-package', isYearly: false);

    expect($user->fresh()->credit)->toBeNull();
});
