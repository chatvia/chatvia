<?php

use App\Identity\Workspace\Models\User;
use Domain\Billing\Actions\Account\DeletePaymentMethodAction;
use Domain\Billing\Models\UserBilling;

it('returns false when no matching payment method can be resolved', function (): void {
    // Observer auto-provisioning is retired (the trial-checkout webhook owns
    // it), so create the UserBilling row explicitly — but leave it without a
    // Stripe customer: findPaymentMethod() swallows the resolution error and
    // returns null, so the action returns false.
    $user = User::factory()->workspace()->create();
    $user->billing()->firstOrCreate([]);

    $result = app(DeletePaymentMethodAction::class)->execute($user, 'pm_does_not_exist');

    expect($result)->toBeFalse();
});

it('throws when called on a user without any billing row at all', function (): void {
    // accountBillingOrFail() throws when neither the user nor its workspace
    // owner has a UserBilling row — exactly the state a factory user is in
    // (registration no longer provisions billing). Wipe defensively in case
    // a future factory state seeds one.
    $user = User::factory()->workspace()->create();
    UserBilling::query()->where('user_id', $user->id)->delete();

    app(DeletePaymentMethodAction::class)->execute($user, 'pm_does_not_exist');
})->throws(RuntimeException::class);
