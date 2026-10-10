<?php

use App\Identity\Workspace\Models\User;
use Domain\Billing\Actions\Subscriptions\SchedulePlanChangeAction;
use Domain\Billing\Data\Subscriptions\SchedulePlanChangeData;
use Domain\Billing\Models\BillingPlan;

it('returns false when the user has no active subscription', function (): void {
    $user = User::factory()->create();

    $result = app(SchedulePlanChangeAction::class)->execute(new SchedulePlanChangeData(
        user: $user,
        package: 'starter',
        isYearly: false,
    ));

    expect($result)->toBeFalse();
});

it('returns false for a chatter with no billing row', function (): void {
    $workspace = User::factory()->workspace()->create();
    $chatter = User::factory()->chatter($workspace)->create();

    $result = app(SchedulePlanChangeAction::class)->execute(new SchedulePlanChangeData(
        user: $chatter,
        package: 'starter',
        isYearly: false,
    ));

    expect($result)->toBeFalse();
});

it('returns false before calling Stripe when the new plan cannot be bought on that cycle', function (string $package, bool $isYearly): void {
    BillingPlan::factory()->archived()->create(['key' => 'old_plan']);
    updateBillingPlan('standard', ['stripe_yearly_price_id' => null]);

    $user = User::factory()->create();
    $user->billing()->firstOrCreate([])->subscriptions()->create([
        'type' => 'default',
        'stripe_id' => 'sub_change_refused',
        'stripe_status' => 'active',
        'stripe_price' => 'price_change_refused',
        'quantity' => 1,
    ]);

    // The account is subscribed, so only the plan check can refuse. No Stripe
    // client is faked: any call to Stripe would fail the test.
    expect($user->refresh()->accountBilling()?->subscribed())->toBeTrue();

    $result = app(SchedulePlanChangeAction::class)->execute(new SchedulePlanChangeData(
        user: $user->refresh(),
        package: $package,
        isYearly: $isYearly,
    ));

    expect($result)->toBeFalse();
})->with([
    'unknown plan' => ['no_such_plan', false],
    'archived plan' => ['old_plan', false],
    'contact-sales plan' => ['enterprise', false],
    'cycle without a price' => ['standard', true],
]);
