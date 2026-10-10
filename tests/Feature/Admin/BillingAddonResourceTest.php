<?php

declare(strict_types=1);

use App\Admin\Resources\BillingAddons\BillingAddonResource;
use App\Admin\Resources\BillingAddons\Pages\EditBillingAddon;
use App\Admin\Resources\BillingAddons\Pages\ListBillingAddons;
use App\Identity\Workspace\Models\User;
use Domain\Billing\Models\BillingAddon;
use Livewire\Livewire;
use Tests\Support\FakeStripeCatalogClient;

/**
 * The Add-ons page of the admin panel: add-ons are listed and edited, never
 * created or deleted (their kinds are fixed in code).
 */
beforeEach(function (): void {
    $this->admin = User::factory()->admin()->create()->fresh();
    $this->stripe = FakeStripeCatalogClient::bind();
    config(['billing.currency' => 'eur']);
});

it('lists the six add-ons and offers no way to create or delete one', function (): void {
    $this->actingAs($this->admin, 'admin')
        ->get(BillingAddonResource::getUrl('index'))
        ->assertOk()
        ->assertSee('Extra AI Agents')
        ->assertSee('Auto Recharge Credits')
        ->assertSee('One-time credit pack');

    Livewire::actingAs($this->admin, 'admin')
        ->test(ListBillingAddons::class)
        ->assertCanSeeTableRecords(BillingAddon::query()->get())
        ->assertActionExists('syncWithStripe')
        ->assertActionDoesNotExist('create');

    expect(BillingAddonResource::canCreate())->toBeFalse()
        ->and(BillingAddonResource::canDelete(BillingAddon::query()->firstOrFail()))->toBeFalse()
        ->and(array_keys(BillingAddonResource::getPages()))->toBe(['index', 'edit']);
});

it('edits an add-on and says it was not sent to Stripe while billing is off', function (): void {
    config(['billing.enabled' => false]);
    $addon = updateBillingAddon('custom_domains', ['stripe_price_id' => 'price_domains']);

    Livewire::actingAs($this->admin, 'admin')
        ->test(EditBillingAddon::class, ['record' => $addon->getRouteKey()])
        ->assertSchemaStateSet(['name' => 'Custom Domain', 'price' => '29.00', 'is_active' => true])
        ->fillForm(['name' => 'Your own domain', 'price' => '35.50', 'billing_unit' => 'per domain/month', 'is_active' => false])
        ->call('save')
        ->assertHasNoFormErrors()
        ->assertNotified('Add-on saved. Not sent to Stripe: paid plans are turned off (Settings → Billing).');

    $addon->refresh();

    expect($addon->name)->toBe('Your own domain')
        ->and($addon->price_cents)->toBe(3550)
        ->and($addon->is_active)->toBeFalse()
        ->and($addon->stripe_price_id)->toBe('price_domains')
        ->and($this->stripe->writes())->toBe(0);
});

it('creates a new Stripe price when the price of an add-on changes', function (): void {
    config(['billing.enabled' => true, 'cashier.secret' => 'sk_test_admin_addons']);
    $this->stripe->seedProduct('prod_members', 'Members');
    $this->stripe->seedPrice('price_members', 'prod_members', 900, ['interval' => 'month']);
    $addon = updateBillingAddon('members', ['stripe_product_id' => null, 'stripe_price_id' => 'price_members']);

    Livewire::actingAs($this->admin, 'admin')
        ->test(EditBillingAddon::class, ['record' => $addon->getRouteKey()])
        ->fillForm(['price' => '12.00'])
        ->call('save')
        ->assertHasNoFormErrors()
        ->assertNotified('Add-on saved and synced with Stripe');

    $addon->refresh();

    expect($this->stripe->createdPrices)->toHaveCount(1)
        ->and($this->stripe->createdPrices[0]['unit_amount'])->toBe(1200)
        ->and($this->stripe->createdPrices[0]['recurring'])->toBe(['interval' => 'month'])
        ->and($addon->stripe_price_id)->not->toBe('price_members')
        ->and($addon->legacy_stripe_price_ids)->toBe(['price_members']);
});

it('opens the edit page', function (): void {
    $addon = BillingAddon::query()->where('key', 'remove_branding')->firstOrFail();

    $this->actingAs($this->admin, 'admin')
        ->get(BillingAddonResource::getUrl('edit', ['record' => $addon]))
        ->assertOk()
        ->assertSee('Remove Chatvia Branding')
        ->assertSee('Monthly subscription');
});

it('keeps non-admins out', function (): void {
    $this->actingAs(User::factory()->create()->fresh(), 'admin')
        ->get(BillingAddonResource::getUrl('index'))
        ->assertForbidden();
});
