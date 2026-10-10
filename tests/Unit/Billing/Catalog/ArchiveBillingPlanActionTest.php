<?php

declare(strict_types=1);

use Domain\Billing\Actions\Catalog\ArchiveBillingPlanAction;
use Domain\Billing\Exceptions\InvalidBillingCatalogException;
use Domain\Billing\Models\BillingPlan;
use Domain\Billing\Services\Catalog\BillingCatalog;

/**
 * Archiving takes a plan off sale but keeps it for its subscribers; the
 * default plan cannot be archived.
 */
it('archives a plan, which still resolves by key and by Stripe price', function (): void {
    $plan = BillingPlan::factory()->create(['stripe_monthly_price_id' => 'price_archive_m']);

    app(ArchiveBillingPlanAction::class)->execute($plan);

    $catalog = app(BillingCatalog::class);

    expect($plan->refresh()->is_active)->toBeFalse()
        ->and($catalog->activePlans()->has($plan->key))->toBeFalse()
        ->and($catalog->plan($plan->key))->not->toBeNull()
        ->and($catalog->planForPrice('price_archive_m')?->package)->toBe($plan->key);
});

it('refuses to archive the default plan', function (): void {
    $default = app(BillingCatalog::class)->defaultPlan();

    expect(fn () => app(ArchiveBillingPlanAction::class)->execute($default))
        ->toThrow(InvalidBillingCatalogException::class, 'default plan cannot be archived');

    expect($default->refresh()->is_active)->toBeTrue();
});
