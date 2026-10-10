<?php

use Domain\Billing\Actions\Addons\SubscribeAddonAction;
use Illuminate\Support\Facades\Exceptions;

/**
 * Add-on checkout errors: a missing add-on price is shown to the user, but any
 * other failure (database, Stripe, missing billing row) is reported and shown
 * as a generic message — its text never reaches the page.
 */
beforeEach(function (): void {
    $this->owner = proPlanAgent()->user;
});

it('shows the not-configured message when the add-on price is missing', function (): void {
    updateBillingAddon('custom_domains', ['stripe_price_id' => null]);

    $this->actingAs($this->owner)
        ->from(route('billing.index'))
        ->get(route('billing.addons.custom-domains', ['quantity' => 1]))
        ->assertRedirect(route('billing.index'))
        ->assertSessionHasErrors(['addon' => 'Custom Domain addon is not configured. Set its Stripe price in the admin panel.']);
});

it('hides database error text and reports the exception', function (): void {
    Exceptions::fake();

    $this->mock(SubscribeAddonAction::class)
        ->shouldReceive('execute')
        ->andThrow(new PDOException('SQLSTATE[42P01]: relation "secret_table" does not exist'));

    $this->actingAs($this->owner)
        ->from(route('billing.index'))
        ->get(route('billing.addons.custom-domains', ['quantity' => 1]))
        ->assertRedirect(route('billing.index'))
        ->assertSessionHasErrors(['addon' => 'Could not start Stripe checkout. Please try again.']);

    Exceptions::assertReported(PDOException::class);
});

it('hides other runtime error text', function (): void {
    $this->mock(SubscribeAddonAction::class)
        ->shouldReceive('execute')
        ->andThrow(new RuntimeException('No UserBilling row for user 42.'));

    $this->actingAs($this->owner)
        ->from(route('billing.index'))
        ->get(route('billing.addons.custom-domains', ['quantity' => 1]))
        ->assertSessionHasErrors(['addon' => 'Could not start Stripe checkout. Please try again.']);
});

it('confirms a quantity change that needed no checkout', function (): void {
    $this->mock(SubscribeAddonAction::class)
        ->shouldReceive('execute')
        ->andReturnNull();

    $this->actingAs($this->owner)
        ->from(route('billing.index'))
        ->get(route('billing.addons.custom-domains', ['quantity' => 2]))
        ->assertRedirect(route('billing.index'))
        ->assertSessionHas('success', 'Custom domain quantity updated.');
});
