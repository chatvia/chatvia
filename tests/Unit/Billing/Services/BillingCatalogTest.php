<?php

use Domain\Billing\Data\Resources\Package\BillingPackageCapabilitiesData;
use Domain\Billing\Data\Subscriptions\PlanSelectionData;
use Domain\Billing\Enums\Addon;
use Domain\Billing\Enums\MessageCreditAddon;
use Domain\Billing\Models\BillingAddon;
use Domain\Billing\Models\BillingPlan;
use Domain\Billing\Services\Catalog\BillingCatalog;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * The one read path for plans and add-ons: cached for every process, kept in
 * memory for one request or job, and cleared whenever a plan or add-on is
 * saved or deleted.
 */
beforeEach(fn () => Cache::flush());

/** A fresh catalog, as the next request or queued job gets it. */
function nextRequestCatalog(): BillingCatalog
{
    app()->forgetScopedInstances();

    return app(BillingCatalog::class);
}

it('is one instance per request or job, and a new one after the worker resets the scope', function (): void {
    $catalog = app(BillingCatalog::class);

    expect(app(BillingCatalog::class))->toBe($catalog)
        ->and(nextRequestCatalog())->not->toBe($catalog);
});

it('reads the plans and add-ons once and serves the next request from the cache', function (): void {
    expect(app(BillingCatalog::class)->plans()->keys()->all())->toBe(['starter', 'standard', 'pro', 'enterprise'])
        ->and(Cache::has(BillingCatalog::CACHE_KEY))->toBeTrue();

    $catalog = nextRequestCatalog();
    DB::enableQueryLog();

    $catalog->plans();
    $catalog->addons();

    expect(DB::getQueryLog())->toBe([])
        ->and($catalog->addons()->keys()->sort()->values()->all())->toBe([
            'auto_recharge_credits', 'custom_domains', 'extra_agents', 'extra_message_credits', 'members', 'remove_branding',
        ]);
});

it('clears the cache when a plan is saved, so the next read sees the change', function (): void {
    app(BillingCatalog::class)->plans();

    updateBillingPlan('pro', ['name' => 'Professional']);

    expect(Cache::has(BillingCatalog::CACHE_KEY))->toBeFalse()
        ->and(app(BillingCatalog::class)->plan('pro')->name)->toBe('Professional')
        ->and(nextRequestCatalog()->plan('pro')->name)->toBe('Professional');
});

it('clears the cache when an add-on is saved or a plan is deleted', function (): void {
    $plan = BillingPlan::factory()->create();
    expect(app(BillingCatalog::class)->plan($plan->key))->not->toBeNull();

    updateBillingAddon('members', ['stripe_price_id' => 'price_members_new']);
    expect(nextRequestCatalog()->addon(Addon::Members)->stripe_price_id)->toBe('price_members_new');

    $plan->delete();
    expect(nextRequestCatalog()->plan($plan->key))->toBeNull();
});

it('finds a plan by a current price before a legacy one', function (): void {
    updateBillingPlan('starter', [
        'stripe_monthly_price_id' => 'price_moved',
        'legacy_stripe_price_ids' => ['price_starter_2025' => 'yearly'],
    ]);
    // The same price is a legacy price of Pro: the plan that has it now wins.
    updateBillingPlan('pro', ['legacy_stripe_price_ids' => ['price_moved' => 'monthly']]);

    $catalog = app(BillingCatalog::class);

    expect($catalog->planForPrice('price_moved'))->toEqual(new PlanSelectionData('starter', isYearly: false))
        ->and($catalog->planForPrice('price_starter_2025'))->toEqual(new PlanSelectionData('starter', isYearly: true))
        ->and($catalog->planForPrice('price_nobody_has'))->toBeNull()
        ->and($catalog->planForPrice(''))->toBeNull();
});

it('never matches the missing Stripe prices of a contact-sales plan', function (): void {
    expect(app(BillingCatalog::class)->plan('enterprise')->stripe_monthly_price_id)->toBeNull()
        ->and(app(BillingCatalog::class)->planForPrice(''))->toBeNull();
});

it('keeps archived plans resolvable but out of the plans a workspace can choose', function (): void {
    updateBillingPlan('standard', ['is_active' => false]);

    $catalog = app(BillingCatalog::class);

    expect($catalog->activePlans()->keys()->all())->toBe(['starter', 'pro', 'enterprise'])
        ->and($catalog->plan('standard')?->key)->toBe('standard')
        ->and(BillingPackageCapabilitiesData::forPlan('standard')->handoff)->toBeTrue();
});

it('has exactly one default plan, and falls back to starter when none is marked', function (): void {
    $catalog = app(BillingCatalog::class);

    expect($catalog->defaultPlan()?->key)->toBe('starter')
        ->and($catalog->defaultPlanKey())->toBe('starter');

    expect(fn () => DB::transaction(fn () => BillingPlan::factory()->create(['is_default' => true])))
        ->toThrow(QueryException::class);

    updateBillingPlan('starter', ['is_default' => false]);
    updateBillingPlan('pro', ['is_default' => true]);

    expect(nextRequestCatalog()->defaultPlanKey())->toBe('pro');

    updateBillingPlan('pro', ['is_default' => false]);

    expect(nextRequestCatalog()->defaultPlan())->toBeNull()
        ->and(app(BillingCatalog::class)->defaultPlanKey())->toBe(BillingCatalog::FALLBACK_PLAN_KEY);
});

it('never lets a plan key change once the plan exists', function (): void {
    expect(fn (): BillingPlan => updateBillingPlan('pro', ['key' => 'professional']))->toThrow(LogicException::class);
});

it('finds an add-on by its current or a legacy Stripe price', function (): void {
    updateBillingAddon('extra_agents', [
        'stripe_price_id' => 'price_agents_now',
        'legacy_stripe_price_ids' => ['price_agents_2025'],
    ]);

    $catalog = app(BillingCatalog::class);

    expect($catalog->addonForPrice('price_agents_now')?->key)->toBe('extra_agents')
        ->and($catalog->addonForPrice('price_agents_2025')?->key)->toBe('extra_agents')
        ->and($catalog->addonForPrice('price_unknown'))->toBeNull();
});

it('gives the add-on enums the price and pack size from their rows', function (): void {
    updateBillingAddon('remove_branding', ['stripe_price_id' => 'price_branding']);
    updateBillingAddon('extra_message_credits', ['stripe_price_id' => 'price_pack', 'unit_messages' => 2500]);

    expect(Addon::RemoveBranding->stripePriceId())->toBe('price_branding')
        ->and(MessageCreditAddon::ExtraMessageCredits->stripePriceId())->toBe('price_pack')
        ->and(MessageCreditAddon::ExtraMessageCredits->unitMessages())->toBe(2500);

    // An add-on that is switched off is not sold, and an empty price is no price.
    updateBillingAddon('remove_branding', ['is_active' => false]);
    updateBillingAddon('extra_message_credits', ['stripe_price_id' => '', 'unit_messages' => null]);

    expect(Addon::RemoveBranding->stripePriceId())->toBeNull()
        ->and(MessageCreditAddon::ExtraMessageCredits->stripePriceId())->toBeNull()
        ->and(MessageCreditAddon::ExtraMessageCredits->unitMessages())->toBe(1000);
});

it('does not cache an empty catalog', function (): void {
    BillingAddon::query()->delete();
    BillingPlan::query()->delete();

    expect(nextRequestCatalog()->plans())->toBeEmpty()
        ->and(Cache::has(BillingCatalog::CACHE_KEY))->toBeFalse();
});

it('returns a copy of the plan capabilities, so a caller cannot change the cached plan', function (): void {
    $capabilities = BillingPackageCapabilitiesData::forPlan('pro');
    $capabilities->retentionDays = 1;

    expect(BillingPackageCapabilitiesData::forPlan('PRO')->retentionDays)->toBe(366)
        ->and(BillingPackageCapabilitiesData::forPlan('no-such-plan')->retentionDays)
        ->toBe(BillingPackageCapabilitiesData::DEFAULT_RETENTION_DAYS);
});
