<?php

use App\Workspace\Models\User;
use App\Billing\Models\UserBilling;
use Domain\Billing\Actions\Account\UpdateBillingInformationAction;
use Domain\Billing\Data\BillingAddressData;
use Domain\Billing\Data\UpdateBillingInformationData;
use Mockery as m;
use Stripe\StripeClient;

afterEach(function (): void {
    m::close();
});

it('persists name and email to the user and forwards them to Stripe', function (): void {
    $user = User::factory()->workspace()->create([
        'name' => 'Old Name',
        'email' => 'old@example.test',
    ]);
    // The action's updateStripeCustomer() path only fires when a Stripe
    // customer already exists, so seed one and stub the StripeClient that
    // Cashier resolves from the container.
    UserBilling::query()
        ->where('user_id', $user->id)
        ->update(['stripe_id' => 'cus_fake']);

    $customers = m::mock();
    $customers->shouldReceive('update')->andReturn((object) ['id' => 'cus_fake']);
    $stripe = m::mock(StripeClient::class);
    $stripe->customers = $customers;
    app()->bind(StripeClient::class, fn () => $stripe);

    $address = new BillingAddressData(
        line1: '1 Test St',
        line2: null,
        city: 'Townsville',
        state: 'TS',
        postal_code: '00000',
        country: 'US',
    );

    app(UpdateBillingInformationAction::class)->execute(new UpdateBillingInformationData(
        user: $user,
        name: 'New Name',
        email: 'new@example.test',
        address: $address,
    ));

    $fresh = $user->fresh();
    expect($fresh->name)->toBe('New Name')
        ->and($fresh->email)->toBe('new@example.test');
});
