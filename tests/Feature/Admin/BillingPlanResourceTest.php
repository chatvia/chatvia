<?php

declare(strict_types=1);

use App\Admin\Billing\StripeSyncState;
use App\Admin\Resources\BillingPlans\BillingPlanResource;
use App\Admin\Resources\BillingPlans\Pages\CreateBillingPlan;
use App\Admin\Resources\BillingPlans\Pages\EditBillingPlan;
use App\Admin\Resources\BillingPlans\Pages\ListBillingPlans;
use App\Identity\Workspace\Models\User;
use Domain\Billing\Models\BillingPlan;
use Domain\Billing\Services\Catalog\BillingCatalog;
use Filament\Actions\Testing\TestAction;
use Livewire\Livewire;
use Tests\Support\FakeStripeCatalogClient;

/**
 * The Plans page of the admin panel: list, create, edit (a price change makes a
 * new Stripe price and keeps the old one for subscribers), archive, delete.
 */
beforeEach(function (): void {
    // fresh(): load every column, as a real request does.
    $this->admin = User::factory()->admin()->create()->fresh();
    $this->stripe = FakeStripeCatalogClient::bind();
    config(['billing.currency' => 'eur']);
});

function billingOn(): void
{
    config(['billing.enabled' => true, 'cashier.secret' => 'sk_test_admin_plans']);
}

/**
 * The form state of a new plan.
 *
 * @return array<string, mixed>
 */
function newPlanForm(array $overrides = []): array
{
    return [
        'key' => 'growth',
        'name' => 'Growth',
        'description' => 'For growing teams.',
        'icon' => 'star',
        'sort_order' => 5,
        'is_active' => true,
        'is_default' => false,
        'is_popular' => false,
        'contact_sales' => false,
        'monthly_price' => '99.00',
        'yearly_price' => '990.00',
        'limits.monthlyMessages' => 8000,
        'limits.yearlyMessages' => 96000,
        'limits.agents' => 5,
        'limits.pages' => 3000,
        'limits.minutes' => 1000,
        'limits.links' => 3000,
        'limits.customDomains' => 1,
        'limits.workspaceUsers' => 10,
        'limits.teams' => 3,
        'capabilities.sentiment' => true,
        'capabilities.audit' => true,
        'capabilities.removeBranding' => true,
        'capabilities.retentionDays' => 120,
        'highlights' => ['sso_saml', 'dedicated_support'],
        // The raw state of a simple repeater: one array per line.
        'extra_features' => [['line' => 'Priority support']],
        ...$overrides,
    ];
}

it('lists the plans with their prices, flags, subscribers and Stripe state', function (): void {
    $starter = updateBillingPlan('starter', ['stripe_product_id' => 'prod_starter', 'stripe_monthly_price_id' => 'price_list_m', 'stripe_yearly_price_id' => 'price_list_y']);
    $owner = User::factory()->create();
    $owner->billing()->firstOrCreate([])->subscriptions()->create([
        'type' => 'default',
        'stripe_id' => 'sub_list_starter',
        'stripe_status' => 'active',
        'stripe_price' => 'price_list_y',
        'quantity' => 1,
    ]);
    $enterprise = BillingPlan::query()->where('key', 'enterprise')->firstOrFail();

    $this->actingAs($this->admin, 'admin')
        ->get(BillingPlanResource::getUrl('index'))
        ->assertOk()
        ->assertSee('Starter')
        ->assertSee('Enterprise')
        ->assertSee('Sync with Stripe');

    Livewire::actingAs($this->admin, 'admin')
        ->test(ListBillingPlans::class)
        ->assertCanSeeTableRecords([$starter, $enterprise])
        ->assertTableColumnStateSet('subscribers', 1, $starter)
        ->assertTableColumnStateSet('subscribers', 0, $enterprise)
        ->assertTableColumnStateSet('stripe', StripeSyncState::Synced, $starter)
        ->assertTableColumnStateSet('stripe', StripeSyncState::NotSold, $enterprise)
        ->assertTableColumnFormattedStateSet('monthly_price_cents', '€49.00', $starter);
});

it('opens the create and edit pages', function (): void {
    $pro = BillingPlan::query()->where('key', 'pro')->firstOrFail();

    $this->actingAs($this->admin, 'admin')
        ->get(BillingPlanResource::getUrl('create'))
        ->assertOk()
        ->assertSee('Lower-case letters, digits and underscores');

    $this->actingAs($this->admin, 'admin')
        ->get(BillingPlanResource::getUrl('edit', ['record' => $pro]))
        ->assertOk()
        ->assertSee('Custom integrations')
        ->assertSee('existing subscribers keep the price they pay now');
});

it('keeps non-admins out', function (): void {
    $workspace = User::factory()->create()->fresh();

    $this->actingAs($workspace, 'admin')
        ->get(BillingPlanResource::getUrl('index'))
        ->assertForbidden();
});

it('creates a plan and its Stripe product and prices', function (): void {
    billingOn();

    Livewire::actingAs($this->admin, 'admin')
        ->test(CreateBillingPlan::class)
        ->fillForm(newPlanForm())
        ->call('create')
        ->assertHasNoFormErrors()
        ->assertNotified('Plan created and synced with Stripe');

    $plan = BillingPlan::query()->where('key', 'growth')->firstOrFail();

    expect($plan->monthly_price_cents)->toBe(9900)
        ->and($plan->yearly_price_cents)->toBe(99000)
        ->and($plan->currency)->toBe('eur')
        ->and($plan->quota_limits['fixed']['teams_limit'])->toBe(3)
        ->and($plan->capabilities->audit)->toBeTrue()
        ->and($plan->capabilities->removeBranding)->toBeTrue()
        ->and($plan->capabilities->tone)->toBeFalse()
        ->and($plan->capabilities->retentionDays)->toBe(120)
        ->and($plan->highlights)->toBe(['sso_saml', 'dedicated_support'])
        ->and($plan->extra_features)->toBe(['Priority support'])
        ->and($this->stripe->createdProducts)->toHaveCount(1)
        ->and(array_column($this->stripe->createdPrices, 'unit_amount'))->toBe([9900, 99000])
        ->and($plan->stripe_monthly_price_id)->not->toBeNull();
});

it('saves a plan without sending it to Stripe while billing is off, and says so', function (): void {
    config(['billing.enabled' => false]);

    Livewire::actingAs($this->admin, 'admin')
        ->test(CreateBillingPlan::class)
        ->fillForm(newPlanForm())
        ->call('create')
        ->assertHasNoFormErrors()
        ->assertNotified('Plan created. Not sent to Stripe: paid plans are turned off (Settings → Billing).');

    expect(BillingPlan::query()->where('key', 'growth')->value('stripe_product_id'))->toBeNull()
        ->and($this->stripe->writes())->toBe(0);
});

it('validates the key of a new plan', function (string $key): void {
    Livewire::actingAs($this->admin, 'admin')
        ->test(CreateBillingPlan::class)
        ->fillForm(newPlanForm(['key' => $key]))
        ->call('create')
        ->assertHasFormErrors(['key']);
})->with(['Has-Capitals', 'starter']);

it('fills the edit form with the plan', function (): void {
    $starter = BillingPlan::query()->where('key', 'starter')->firstOrFail();

    Livewire::actingAs($this->admin, 'admin')
        ->test(EditBillingPlan::class, ['record' => $starter->getRouteKey()])
        ->assertOk()
        ->assertSchemaStateSet([
            'key' => 'starter',
            'name' => 'Starter',
            'monthly_price' => '49.00',
            'yearly_price' => '468.00',
            'is_default' => true,
            'limits.monthlyMessages' => $starter->quota_limits['monthly']['messages_limit'],
            'limits.teams' => $starter->quota_limits['fixed']['teams_limit'],
            'capabilities.retentionDays' => $starter->capabilities->retentionDays,
        ]);
});

it('creates a new Stripe price when a price changes and keeps the old one for subscribers', function (): void {
    billingOn();
    $this->stripe->seedProduct('prod_pro', 'Pro');
    $this->stripe->seedPrice('price_pro_m', 'prod_pro', 49900, ['interval' => 'month']);
    $this->stripe->seedPrice('price_pro_y', 'prod_pro', 478800, ['interval' => 'year']);
    $pro = updateBillingPlan('pro', ['stripe_product_id' => null, 'stripe_monthly_price_id' => 'price_pro_m', 'stripe_yearly_price_id' => 'price_pro_y']);

    Livewire::actingAs($this->admin, 'admin')
        ->test(EditBillingPlan::class, ['record' => $pro->getRouteKey()])
        ->fillForm(['monthly_price' => '599.00', 'name' => 'Pro Max', 'key' => 'renamed'])
        ->call('save')
        ->assertHasNoFormErrors()
        ->assertNotified('Plan saved and synced with Stripe');

    $pro->refresh();

    expect($pro->key)->toBe('pro')
        ->and($pro->name)->toBe('Pro Max')
        ->and($pro->monthly_price_cents)->toBe(59900)
        ->and($this->stripe->createdPrices)->toHaveCount(1)
        ->and($this->stripe->createdPrices[0]['unit_amount'])->toBe(59900)
        ->and($pro->stripe_monthly_price_id)->not->toBe('price_pro_m')
        ->and($pro->stripe_yearly_price_id)->toBe('price_pro_y')
        ->and($pro->legacy_stripe_price_ids)->toBe(['price_pro_m' => 'monthly'])
        ->and(app(BillingCatalog::class)->planForPrice('price_pro_m')?->package)->toBe('pro')
        ->and($this->stripe->productStore['prod_pro']->name)->toBe('Pro Max');
});

it('archives a plan from the list', function (): void {
    $plan = BillingPlan::factory()->create();

    Livewire::actingAs($this->admin, 'admin')
        ->test(ListBillingPlans::class)
        ->callAction(TestAction::make('archive')->table($plan))
        ->assertNotified("{$plan->name} archived");

    expect($plan->refresh()->is_active)->toBeFalse();
});

it('refuses to archive the default plan', function (): void {
    $default = BillingPlan::query()->where('is_default', true)->firstOrFail();

    Livewire::actingAs($this->admin, 'admin')
        ->test(ListBillingPlans::class)
        ->callAction(TestAction::make('archive')->table($default))
        ->assertNotified('Not archived');

    expect($default->refresh()->is_active)->toBeTrue();
});

it('refuses to leave the panel without a default plan', function (): void {
    $default = BillingPlan::query()->where('is_default', true)->firstOrFail();

    Livewire::actingAs($this->admin, 'admin')
        ->test(EditBillingPlan::class, ['record' => $default->getRouteKey()])
        ->fillForm(['is_default' => false])
        ->call('save')
        ->assertNotified('Plan not saved');

    expect($default->refresh()->is_default)->toBeTrue();
});

it('deletes only a plan no workspace ever used', function (): void {
    $unused = BillingPlan::factory()->create(['stripe_monthly_price_id' => null, 'stripe_yearly_price_id' => null]);
    $starter = BillingPlan::query()->where('key', 'starter')->firstOrFail();
    $used = BillingPlan::factory()->create();
    User::factory()->create()->billing()->firstOrCreate([])->update(['plan' => $used->key]);

    Livewire::actingAs($this->admin, 'admin')
        ->test(EditBillingPlan::class, ['record' => $starter->getRouteKey()])
        ->assertActionHidden('delete');

    Livewire::actingAs($this->admin, 'admin')
        ->test(EditBillingPlan::class, ['record' => $used->getRouteKey()])
        ->assertActionHidden('delete');

    Livewire::actingAs($this->admin, 'admin')
        ->test(EditBillingPlan::class, ['record' => $unused->getRouteKey()])
        ->callAction('delete');

    expect(BillingPlan::query()->whereKey($unused->getKey())->exists())->toBeFalse()
        ->and(BillingPlan::query()->whereKey($used->getKey())->exists())->toBeTrue();
});

it('syncs every plan and add-on from the list, and says when billing is off', function (): void {
    config(['billing.enabled' => false]);

    Livewire::actingAs($this->admin, 'admin')
        ->test(ListBillingPlans::class)
        ->callAction('syncWithStripe')
        ->assertNotified('Not sent to Stripe: paid plans are turned off (Settings → Billing).');

    expect($this->stripe->writes())->toBe(0);
});

it('warns before saving when the plan will not reach Stripe or will change currency', function (): void {
    config(['billing.enabled' => false]);
    $plan = BillingPlan::factory()->create(['currency' => 'usd']);

    Livewire::actingAs($this->admin, 'admin')
        ->test(EditBillingPlan::class, ['record' => $plan->getRouteKey()])
        ->assertSee('Saving does not send anything to Stripe: paid plans are turned off (Settings → Billing).')
        ->assertSee('This is priced in USD, but the billing currency is EUR.');

    billingOn();

    Livewire::actingAs($this->admin, 'admin')
        ->test(EditBillingPlan::class, ['record' => BillingPlan::query()->where('key', 'starter')->value('id')])
        ->assertDontSee('Saving does not send anything to Stripe')
        ->assertDontSee('but the billing currency is');
});

it('checks the catalog against Stripe without changing anything', function (int $autoRechargeCents, string $title): void {
    billingOn();
    updateBillingAddon('auto_recharge_credits', ['price_cents' => $autoRechargeCents]);
    updateBillingAddon('extra_message_credits', ['price_cents' => 900]);
    $this->stripe->seedLiveCatalog(creditPackAmount: 900);

    Livewire::actingAs($this->admin, 'admin')
        ->test(ListBillingPlans::class)
        ->callAction('checkAgainstStripe')
        ->assertNotified($title);

    expect($this->stripe->writes())->toBe(0)
        ->and(BillingPlan::query()->where('key', 'starter')->value('stripe_product_id'))->toBeNull();
})->with([
    'every price matches' => [900, 'Every price in Stripe matches'],
    'a saved amount differs' => [1000, 'Sync with Stripe would change prices'],
]);

it('saves the default plan, which stays the active default', function (): void {
    config(['billing.enabled' => false]);
    $default = BillingPlan::query()->where('is_default', true)->firstOrFail();

    Livewire::actingAs($this->admin, 'admin')
        ->test(EditBillingPlan::class, ['record' => $default->getRouteKey()])
        ->assertFormFieldIsDisabled('is_default')
        ->assertFormFieldIsDisabled('is_active')
        ->fillForm(['name' => 'Starter (renamed)'])
        ->call('save')
        ->assertHasNoFormErrors()
        ->assertNotified('Plan saved. Not sent to Stripe: paid plans are turned off (Settings → Billing).');

    $default->refresh();

    expect($default->name)->toBe('Starter (renamed)')
        ->and($default->is_default)->toBeTrue()
        ->and($default->is_active)->toBeTrue();
});
