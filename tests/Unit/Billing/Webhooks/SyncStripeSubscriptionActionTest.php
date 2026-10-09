<?php

use App\Identity\Workspace\Models\User;
use Domain\Billing\Actions\Webhooks\SyncStripeSubscriptionAction;
use Mockery as m;
use Stripe\StripeClient;
use Stripe\Subscription as StripeSubscription;

afterEach(function (): void {
    m::close();
});

function sssStripe(string $status): void
{
    $subscriptions = m::mock();
    $subscriptions->shouldReceive('retrieve')
        ->with('sub_sync', ['expand' => ['items.data.price']])
        ->andReturn(StripeSubscription::constructFrom([
            'id' => 'sub_sync',
            'status' => $status,
            'trial_end' => null,
            'metadata' => [],
            'items' => ['object' => 'list', 'data' => [[
                'id' => 'si_sync',
                'quantity' => 1,
                'price' => ['id' => 'price_pro_monthly', 'product' => 'prod_pro'],
            ]]],
        ]));

    $stripe = m::mock(StripeClient::class);
    $stripe->subscriptions = $subscriptions;
    app()->bind(StripeClient::class, fn () => $stripe);
}

it('creates the local subscription and item, and updates them in place on a re-run', function (): void {
    $billing = User::factory()->workspace()->create()->billing()->firstOrCreate([]);

    sssStripe('trialing');
    app(SyncStripeSubscriptionAction::class)->execute($billing, 'sub_sync');

    sssStripe('active');
    $subscription = app(SyncStripeSubscriptionAction::class)->execute($billing, 'sub_sync');

    expect($billing->subscriptions()->count())->toBe(1)
        ->and($subscription->type)->toBe('default')
        ->and($subscription->stripe_status)->toBe('active')
        ->and($subscription->stripe_price)->toBe('price_pro_monthly')
        ->and($subscription->items()->count())->toBe(1);
});
