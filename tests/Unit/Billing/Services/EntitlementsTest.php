<?php

use App\Identity\Workspace\Models\User;
use Domain\Billing\Contracts\Entitlements;
use Domain\Billing\Models\UserCredit;
use Domain\Billing\Models\UserQuota;
use Domain\Billing\Services\Entitlements\StripeEntitlements;
use Domain\Billing\Services\Entitlements\UnlimitedEntitlements;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * The Entitlements port picks its driver from `billing.enabled` on every
 * resolve, and the Stripe driver reads plans, credits and quota rows exactly
 * as the code it replaced did.
 */
beforeEach(fn () => Cache::flush());

it('resolves the Stripe driver when billing is on and the unlimited driver when it is off', function (): void {
    config(['billing.enabled' => true]);
    expect(app(Entitlements::class))->toBeInstanceOf(StripeEntitlements::class);

    config(['billing.enabled' => false]);
    expect(app(Entitlements::class))->toBeInstanceOf(UnlimitedEntitlements::class);
});

it('reads the plan quota row, then the default package, for the Stripe driver', function (): void {
    $withRow = User::factory()->workspace()->create();
    UserQuota::create([
        ...config('billing.packages.pro.quota_limits.fixed'),
        'user_id' => $withRow->id,
        'extra_members_limit' => 3,
    ]);
    $withoutRow = User::factory()->workspace()->create();

    $stripe = app(StripeEntitlements::class);

    expect($stripe->quotaLimit($withRow->fresh(), 'teams_limit'))->toBe(10)
        ->and($withRow->fresh()->totalWorkspaceUsersLimit())->toBe(103)
        ->and($stripe->quotaLimit($withoutRow, 'teams_limit'))->toBe(1)
        ->and($stripe->quotaLimit($withoutRow, 'custom_domains_limit'))->toBe(0);
});

it('reads the plan from the Stripe price for the Stripe driver', function (): void {
    $priceId = 'price_entitlements_'.Str::random(6);
    config(['billing.packages.standard.stripe_monthly_price_id' => $priceId]);

    $owner = User::factory()->workspace()->create();
    $owner->billing()->create([])->subscriptions()->create([
        'type' => 'default',
        'stripe_id' => 'sub_entitlements_'.Str::random(6),
        'stripe_status' => 'active',
        'stripe_price' => $priceId,
        'quantity' => 1,
    ]);
    $owner = $owner->fresh();
    $employee = User::factory()->employee($owner)->create();

    $stripe = app(StripeEntitlements::class);

    expect($stripe->hasActiveBilling($owner))->toBeTrue()
        ->and($stripe->capabilities($owner)->evals)->toBeTrue()
        ->and($stripe->capabilities($owner)->audit)->toBeFalse()
        ->and($stripe->capabilities($employee)->evals)->toBeTrue()
        ->and($stripe->hasActiveBilling(User::factory()->workspace()->create()))->toBeFalse();
});

it('reads remaining messages from the credit pool for the Stripe driver', function (): void {
    $owner = User::factory()->workspace()->create();
    $credit = UserCredit::factory()->create([
        'user_id' => $owner->id,
        'messages' => 50,
        'started_at' => now()->subDay(),
        'ends_at' => now()->addDays(20),
    ]);
    $credit->usage()->create(['messages' => 8]);

    expect(app(StripeEntitlements::class)->availableMessages($owner))->toBe(42);
});

it('has no limits and every feature in the unlimited driver', function (): void {
    config(['billing.unlimited_retention_days' => 500]);
    $user = User::factory()->workspace()->create();

    $unlimited = app(UnlimitedEntitlements::class);

    expect($unlimited->enforced())->toBeFalse()
        ->and($unlimited->hasActiveBilling($user))->toBeTrue()
        ->and($unlimited->hasMessageCapacity($user->id))->toBeTrue()
        ->and($unlimited->availableMessages($user))->toBeNull()
        ->and($unlimited->quotaLimit($user, 'pages_limit'))->toBe(Entitlements::UNLIMITED)
        ->and($unlimited->capabilities($user)->siemWebhook)->toBeTrue()
        ->and($unlimited->capabilities($user)->retentionDays)->toBe(500);
});
