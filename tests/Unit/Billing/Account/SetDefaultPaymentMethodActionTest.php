<?php

use App\Workspace\Models\User;
use Domain\Billing\Actions\Account\SetDefaultPaymentMethodAction;

it('is a silent no-op when the user has no billing row at all', function (): void {
    // Chatters never get a UserBilling row (UserObserver only provisions for
    // Workspace + Admin). The null-safe chain in the action lets it skip the
    // Stripe call without throwing.
    $workspace = User::factory()->workspace()->create();
    $chatter = User::factory()->chatter($workspace)->create();

    app(SetDefaultPaymentMethodAction::class)->execute($chatter, 'pm_fake');

    expect(true)->toBeTrue();
});
