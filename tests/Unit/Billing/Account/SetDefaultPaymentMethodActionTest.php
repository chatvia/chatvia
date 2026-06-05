<?php

use App\Identity\Workspace\Models\User;
use Domain\Billing\Actions\Account\SetDefaultPaymentMethodAction;

it('is a silent no-op when the user has no billing row at all', function (): void {
    // Chatters never get a UserBilling row (UserObserver only provisions for
    // Workspace + Admin). The null-safe chain in the action lets it skip the
    // Stripe call without throwing.
    $workspace = User::factory()->workspace()->create();
    $chatter = User::factory()->chatter($workspace)->create();

    app(SetDefaultPaymentMethodAction::class)->execute($chatter, 'pm_fake');

    // The no-op must not have provisioned a billing row as a side effect.
    expect($chatter->fresh()->billing)->toBeNull();
});
