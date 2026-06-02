<?php

use App\Workspace\Models\User;
use App\Billing\Listeners\StripeEventListener;
use App\Billing\Models\UserCredit;
use Laravel\Cashier\Events\WebhookReceived;

function makeSubscriptionCyclePayload(string $eventId, string $customerId, string $invoiceId = 'in_test_001'): array
{
    return [
        'id' => $eventId,
        'type' => 'invoice.payment_succeeded',
        'data' => [
            'object' => [
                'id' => $invoiceId,
                'customer' => $customerId,
                'billing_reason' => 'subscription_cycle',
                'subscription_details' => [
                    'metadata' => [
                        'to_package' => 'starter',
                        'is_yearly' => 'false',
                    ],
                ],
            ],
        ],
    ];
}

it('grants credit only once when the same Stripe event is delivered twice', function (): void {
    $user = User::factory()->create();
    $user->billing()->update(['stripe_id' => 'cus_replay_test']);

    $payload = makeSubscriptionCyclePayload('evt_replay_test_1', 'cus_replay_test');

    $listener = app(StripeEventListener::class);

    $listener->handle(new WebhookReceived($payload));
    $countAfterFirst = UserCredit::where('user_id', $user->id)->count();

    $listener->handle(new WebhookReceived($payload));
    $countAfterReplay = UserCredit::where('user_id', $user->id)->count();

    expect($countAfterFirst)->toBe(1)
        ->and($countAfterReplay)->toBe(1);
});

it('grants credit again for a different Stripe event id', function (): void {
    $user = User::factory()->create();
    $user->billing()->update(['stripe_id' => 'cus_replay_test_2']);

    $listener = app(StripeEventListener::class);

    $listener->handle(new WebhookReceived(
        makeSubscriptionCyclePayload('evt_first', 'cus_replay_test_2', 'in_first')
    ));

    $listener->handle(new WebhookReceived(
        makeSubscriptionCyclePayload('evt_second', 'cus_replay_test_2', 'in_second')
    ));

    // Second distinct event expires the first credit and creates a new active one.
    expect(UserCredit::where('user_id', $user->id)->count())->toBe(2)
        ->and($user->fresh()->credit)->not->toBeNull();
});
