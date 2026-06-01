<?php

use App\Auth\Models\User;
use Domain\Billing\Actions\Account\DeletePaymentMethodAction;

it('returns false when no matching payment method can be resolved', function (): void {
    // The workspace owner has a UserBilling row (auto-provisioned by the
    // observer) but no Stripe customer; findPaymentMethod() swallows the
    // resolution error and returns null, so the action returns false.
    $user = User::factory()->workspace()->create();

    $result = app(DeletePaymentMethodAction::class)->execute($user, 'pm_does_not_exist');

    expect($result)->toBeFalse();
});

it('throws when called on a user without any billing row at all', function (): void {
    // accountBillingOrFail() throws when neither the user nor its workspace
    // owner has a UserBilling row. We force the situation by wiping the row
    // the observer would normally provision.
    $user = User::factory()->workspace()->create();
    \App\Billing\Models\UserBilling::query()->where('user_id', $user->id)->delete();

    app(DeletePaymentMethodAction::class)->execute($user, 'pm_does_not_exist');
})->throws(\RuntimeException::class);
