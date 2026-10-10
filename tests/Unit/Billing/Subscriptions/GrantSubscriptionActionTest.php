<?php

use App\Identity\Workspace\Models\User;
use Domain\Billing\Actions\Subscriptions\GrantSubscriptionAction;
use Domain\Billing\Data\Subscriptions\PlanSelectionData;
use Domain\Billing\Models\BillingPlan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

beforeEach(function (): void {
    Cache::flush();

    // A controlled package so the test does not depend on real Stripe prices.
    BillingPlan::factory()->create([
        'key' => 'test_pkg',
        'quota_limits' => [
            'monthly' => ['messages_limit' => 5000],
            'yearly' => ['messages_limit' => 60000],
            'fixed' => [
                'agents_limit' => 3,
                'pages_limit' => 1000,
                'minutes_limit' => 60,
                'custom_domains_limit' => 0,
                'workspace_users_limit' => 1,
                'links_limit' => 50,
                'teams_limit' => 1,
            ],
        ],
    ]);
});

it('grants the package quota, plan label and message credits', function (): void {
    $user = User::factory()->workspace()->create();

    app(GrantSubscriptionAction::class)->execute($user, new PlanSelectionData('test_pkg', isYearly: false));

    $user->refresh();

    expect($user->accountBilling()->plan)->toBe('test_pkg')
        ->and((int) $user->quota->agents_limit)->toBe(3)
        ->and((int) $user->quota->pages_limit)->toBe(1000)
        ->and((int) $user->quota->teams_limit)->toBe(1)
        ->and((int) $user->credit->messages)->toBe(5000);
});

it('uses the yearly credit allotment and a one-year expiry for yearly grants', function (): void {
    $user = User::factory()->workspace()->create();

    app(GrantSubscriptionAction::class)->execute($user, new PlanSelectionData('test_pkg', isYearly: true));

    $user->refresh();

    expect((int) $user->credit->messages)->toBe(60000)
        ->and($user->credit->ends_at->isAfter(now()->addMonths(6)))->toBeTrue();
});

it('logs a warning and grants nothing for an unknown package', function (): void {
    // StartTrialCheckoutAction stores strtolower($package) in Stripe metadata
    // and renewals look that key back up in billing_plans — a plan key that
    // no longer exists makes in-flight subscriptions land on this branch.
    Log::spy();

    $user = User::factory()->workspace()->create();
    $quotaBefore = $user->quota?->toArray();

    app(GrantSubscriptionAction::class)->execute($user, new PlanSelectionData('no-such-package', isYearly: false));

    Log::shouldHaveReceived('warning')
        ->once()
        ->with('Invalid package configuration', ['package' => 'no-such-package', 'cycle' => 'monthly']);

    $user->refresh();

    expect($user->credit)->toBeNull()
        ->and($user->quota?->toArray())->toBe($quotaBefore);
});

it('still grants an archived plan to the workspaces subscribed to it', function (): void {
    BillingPlan::query()->where('key', 'test_pkg')->firstOrFail()->update(['is_active' => false]);
    $user = User::factory()->workspace()->create();

    app(GrantSubscriptionAction::class)->execute($user, new PlanSelectionData('test_pkg', isYearly: false));

    expect((int) $user->fresh()->credit->messages)->toBe(5000);
});
