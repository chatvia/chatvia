<?php

use App\Identity\Workspace\Models\User;
use Domain\Billing\Actions\Webhooks\GrantRenewalAction;
use Domain\Billing\Data\Stripe\PaidInvoiceData;
use Domain\Billing\Models\UserCredit;
use Illuminate\Support\Facades\Log;
use Mockery as m;
use Stripe\StripeClient;
use Stripe\Subscription as StripeSubscription;

afterEach(function (): void {
    m::close();
});

/**
 * @param  array<string, mixed>  $metadata
 */
function grInvoice(string $customerId, string $reason, array $metadata = [], string $priceId = 'price_standard_monthly'): PaidInvoiceData
{
    return PaidInvoiceData::fromInvoice([
        'id' => 'in_'.$customerId,
        'customer' => $customerId,
        'billing_reason' => $reason,
        'subscription' => 'sub_'.$customerId,
        'subscription_details' => ['metadata' => $metadata],
        'lines' => ['object' => 'list', 'data' => [['id' => 'il_1', 'price' => ['id' => $priceId]]]],
    ]);
}

beforeEach(function (): void {
    updateBillingPlan('standard', [
        'stripe_monthly_price_id' => 'price_standard_monthly',
        'stripe_yearly_price_id' => 'price_standard_yearly',
    ]);
});

it('grants the plan the renewal invoice paid for', function (): void {
    $owner = User::factory()->workspace()->create();
    $owner->billing()->updateOrCreate([], ['stripe_id' => 'cus_renew']);

    app(GrantRenewalAction::class)->execute(grInvoice('cus_renew', 'subscription_cycle'));

    expect($owner->fresh()->billing->plan)->toBe('standard')
        ->and(UserCredit::query()->where('user_id', $owner->id)->value('messages'))->toBe(15000);
});

it('skips the first invoice of a subscription, which the trial checkout already granted', function (): void {
    $owner = User::factory()->workspace()->create();
    $owner->billing()->updateOrCreate([], ['stripe_id' => 'cus_first']);

    app(GrantRenewalAction::class)->execute(grInvoice('cus_first', 'subscription_create'));

    expect(UserCredit::query()->where('user_id', $owner->id)->exists())->toBeFalse();
});

it('logs and skips an invoice for a customer it does not know', function (): void {
    Log::spy();

    app(GrantRenewalAction::class)->execute(grInvoice('cus_unknown', 'subscription_cycle'));

    Log::shouldHaveReceived('warning')->with('User not found for invoice payment', ['customer' => 'cus_unknown']);
});

it('clears a scheduled plan change once the renewal applied it', function (): void {
    $owner = User::factory()->workspace()->create();
    $billing = $owner->billing()->updateOrCreate([], ['stripe_id' => 'cus_applied']);
    $billing->subscriptions()->create([
        'type' => 'default',
        'stripe_id' => 'sub_cus_applied',
        'stripe_status' => 'active',
        'stripe_price' => 'price_standard_monthly',
        'quantity' => 1,
    ]);

    $subscriptions = m::mock();
    $subscriptions->shouldReceive('update')
        ->once()
        ->with('sub_cus_applied', m::on(fn (array $options): bool => $options['metadata']['pending_change'] === 'false'
            && array_key_exists('applied_at', $options['metadata'])
            && $options['metadata']['to_package'] === null))
        ->andReturn(StripeSubscription::constructFrom(['id' => 'sub_cus_applied']));
    $stripe = m::mock(StripeClient::class);
    $stripe->subscriptions = $subscriptions;
    app()->bind(StripeClient::class, fn () => $stripe);

    app(GrantRenewalAction::class)->execute(grInvoice('cus_applied', 'subscription_cycle', [
        'pending_change' => 'true',
        'to_package' => 'standard',
    ]));

    expect($owner->fresh()->billing->plan)->toBe('standard');
});

it('grants the plan a renewal on a legacy price paid for, with that price interval', function (): void {
    updateBillingPlan('pro', ['legacy_stripe_price_ids' => ['price_pro_2025_yearly' => 'yearly']]);
    $owner = User::factory()->workspace()->create();
    $owner->billing()->updateOrCreate([], ['stripe_id' => 'cus_legacy']);

    app(GrantRenewalAction::class)->execute(grInvoice('cus_legacy', 'subscription_cycle', priceId: 'price_pro_2025_yearly'));

    expect($owner->fresh()->billing->plan)->toBe('pro')
        ->and(UserCredit::query()->where('user_id', $owner->id)->value('messages'))->toBe(648000);
});

it('skips a renewal that bills an add-on price, even without add-on metadata', function (): void {
    updateBillingAddon('members', [
        'stripe_price_id' => 'price_members_now',
        'legacy_stripe_price_ids' => ['price_members_2025'],
    ]);
    $owner = User::factory()->workspace()->create();
    $billing = $owner->billing()->updateOrCreate([], ['stripe_id' => 'cus_addon_price']);
    // The plan subscription the old fallback would have re-granted.
    $billing->subscriptions()->create([
        'type' => 'default',
        'stripe_id' => 'sub_plan_cus_addon_price',
        'stripe_status' => 'active',
        'stripe_price' => 'price_standard_monthly',
        'quantity' => 1,
    ]);

    app(GrantRenewalAction::class)->execute(grInvoice('cus_addon_price', 'subscription_cycle', priceId: 'price_members_2025'));

    expect(UserCredit::query()->where('user_id', $owner->id)->exists())->toBeFalse();
});
