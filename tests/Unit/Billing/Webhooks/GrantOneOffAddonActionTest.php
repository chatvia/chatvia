<?php

use App\Identity\Workspace\Models\User;
use Domain\Billing\Actions\Webhooks\GrantOneOffAddonAction;
use Domain\Billing\Data\Stripe\CompletedCheckoutData;
use Domain\Billing\Data\Stripe\StripeMetadata;
use Domain\Billing\Models\UserAddon;

function gooCheckout(string $customerId, string $packId, int $messages = 2000): CompletedCheckoutData
{
    return new CompletedCheckoutData(
        sessionId: 'cs_pack_'.$customerId,
        customerId: $customerId,
        subscriptionId: null,
        metadata: new StripeMetadata([
            StripeMetadata::TYPE => 'addon',
            StripeMetadata::ID => $packId,
            StripeMetadata::MESSAGES => $messages,
        ]),
    );
}

it('grants a bought extra-message pack once per checkout session', function (): void {
    $owner = User::factory()->workspace()->create();
    $owner->billing()->updateOrCreate([], ['stripe_id' => 'cus_pack']);

    $first = app(GrantOneOffAddonAction::class)->execute(gooCheckout('cus_pack', 'extra_message_credits'));
    $again = app(GrantOneOffAddonAction::class)->execute(gooCheckout('cus_pack', 'extra_message_credits'));

    expect($first->id)->toBe($again->id)
        ->and($first->amount)->toBe(2000)
        ->and(UserAddon::query()->where('user_id', $owner->id)->count())->toBe(1);
});

it('ignores auto-recharge packs and unknown customers', function (): void {
    $owner = User::factory()->workspace()->create();
    $owner->billing()->updateOrCreate([], ['stripe_id' => 'cus_auto']);

    expect(app(GrantOneOffAddonAction::class)->execute(gooCheckout('cus_auto', 'auto_recharge_credits')))->toBeNull()
        ->and(app(GrantOneOffAddonAction::class)->execute(gooCheckout('cus_nobody', 'extra_message_credits')))->toBeNull()
        ->and(UserAddon::query()->count())->toBe(0);
});
