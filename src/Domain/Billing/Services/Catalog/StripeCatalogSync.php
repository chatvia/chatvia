<?php

declare(strict_types=1);

namespace Domain\Billing\Services\Catalog;

use Domain\Billing\Data\Catalog\StripeCatalogSyncData;
use Domain\Billing\Data\Catalog\StripePriceSpecData;
use Domain\Billing\Data\Catalog\StripeProductSpecData;
use Domain\Billing\Enums\Addon;
use Domain\Billing\Enums\BillingInterval;
use Domain\Billing\Enums\MessageCreditAddon;
use Domain\Billing\Enums\StripeSyncBlocker;
use Domain\Billing\Models\BillingAddon;
use Domain\Billing\Models\BillingPlan;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Database\Eloquent\Model;
use Laravel\Cashier\Cashier;
use Stripe\Exception\ApiErrorException;
use Stripe\Price;
use Stripe\Product;
use Stripe\StripeClient;

/**
 * Makes Stripe sell the plans and add-ons at the prices saved in the admin
 * panel. Each plan and add-on gets a Stripe product (named after it) and a
 * price per way it is sold:
 *  - a plan: a monthly and a yearly subscription price; none for a
 *    contact-sales plan or a cycle without an amount;
 *  - a subscription add-on ({@see Addon}): a monthly subscription price;
 *  - a message-credit pack ({@see MessageCreditAddon}): a one-time price.
 *
 * Stripe prices cannot change. When the amount, currency or period of the
 * live Stripe price differs from the saved one, a new price is created and the
 * old id moves to `legacy_stripe_price_ids`: new customers pay the new price,
 * existing subscriptions keep theirs and still resolve to the plan. The live
 * price is always read from Stripe first, so an unchanged amount never makes a
 * new price, and running the sync again with nothing changed creates nothing.
 *
 * Nothing is sent while paid plans are off or no Stripe secret key is saved
 * ({@see blocker()}); nothing in Stripe is ever deleted or archived. A Stripe
 * error stops the sync of that plan or add-on and is reported in the result;
 * what was created before it is kept.
 *
 * `previewPlan()` / `previewAddon()` make the same comparison with the live
 * Stripe objects but write nothing, neither in Stripe nor on the plan or
 * add-on: they report what a sync would do.
 */
final readonly class StripeCatalogSync
{
    public function __construct(
        private Config $config,
    ) {}

    /** Why nothing can be sent to Stripe now, or null when it can. */
    public function blocker(): ?StripeSyncBlocker
    {
        return match (true) {
            ! $this->config->get('billing.enabled') => StripeSyncBlocker::BillingOff,
            blank($this->config->get('cashier.secret')) => StripeSyncBlocker::NoSecretKey,
            default => null,
        };
    }

    public function plan(BillingPlan $plan): StripeCatalogSyncData
    {
        return $this->apply($plan, fn (StripeCatalogSyncRun $run) => $this->syncPlan($run, $plan));
    }

    /** What {@see plan()} would do, without writing anything. */
    public function previewPlan(BillingPlan $plan): StripeCatalogSyncData
    {
        $copy = clone $plan;

        return $this->preview(fn (StripeCatalogSyncRun $run) => $this->syncPlan($run, $copy));
    }

    public function addon(BillingAddon $addon): StripeCatalogSyncData
    {
        return $this->apply($addon, fn (StripeCatalogSyncRun $run) => $this->syncAddon($run, $addon));
    }

    /** What {@see Addon()} would do, without writing anything. */
    public function previewAddon(BillingAddon $addon): StripeCatalogSyncData
    {
        $copy = clone $addon;

        return $this->preview(fn (StripeCatalogSyncRun $run) => $this->syncAddon($run, $copy));
    }

    /**
     * Brings the plan's product and prices in line, setting the new ids on
     * the plan (not saved here).
     *
     * @throws ApiErrorException
     */
    private function syncPlan(StripeCatalogSyncRun $run, BillingPlan $plan): void
    {
        $product = StripeProductSpecData::forPlan($plan);
        $specs = array_map(fn (BillingInterval $interval): StripePriceSpecData => new StripePriceSpecData(
            product: $product,
            slot: $interval->value,
            amount: $plan->contact_sales ? null : ($interval->isYearly() ? $plan->yearly_price_cents : $plan->monthly_price_cents),
            currency: $plan->currency,
            recurringInterval: $interval->isYearly() ? StripePriceSpecData::RECURRING_YEAR : StripePriceSpecData::RECURRING_MONTH,
            currentPriceId: $plan->stripePriceId($interval),
        ), BillingInterval::cases());

        $productId = $this->productFor($run, $product, $plan->stripe_product_id, $specs);
        $plan->stripe_product_id = $productId;
        $legacy = $plan->legacy_stripe_price_ids;

        foreach ($specs as $spec) {
            $interval = BillingInterval::from($spec->slot);
            $priceId = $productId !== null && $spec->isWanted() ? $run->priceFor($productId, $spec) : null;

            if ($priceId === $spec->currentPriceId) {
                continue;
            }

            if ($spec->currentPriceId !== null) {
                $legacy[$spec->currentPriceId] = $interval->value;
                $run->retire($spec->currentPriceId);
            }

            if ($priceId !== null) {
                unset($legacy[$priceId]);
            }

            $plan->{$interval->isYearly() ? 'stripe_yearly_price_id' : 'stripe_monthly_price_id'} = $priceId;
            $plan->legacy_stripe_price_ids = $legacy;
        }
    }

    /**
     * Brings the add-on's product and price in line, setting the new ids on
     * the add-on (not saved here).
     *
     * @throws ApiErrorException
     */
    private function syncAddon(StripeCatalogSyncRun $run, BillingAddon $addon): void
    {
        $product = StripeProductSpecData::forAddon($addon);
        $oneTime = MessageCreditAddon::tryFrom($addon->key) instanceof MessageCreditAddon;
        $spec = new StripePriceSpecData(
            product: $product,
            slot: $oneTime ? 'one_time' : BillingInterval::Monthly->value,
            amount: $addon->price_cents,
            currency: $addon->currency,
            recurringInterval: $oneTime ? null : StripePriceSpecData::RECURRING_MONTH,
            currentPriceId: filled($addon->stripe_price_id) ? $addon->stripe_price_id : null,
        );

        $productId = $this->productFor($run, $product, $addon->stripe_product_id, [$spec]);
        $addon->stripe_product_id = $productId;
        $priceId = $productId !== null && $spec->isWanted() ? $run->priceFor($productId, $spec) : null;

        if ($priceId === $spec->currentPriceId) {
            return;
        }

        $legacy = $addon->legacy_stripe_price_ids;

        if ($spec->currentPriceId !== null) {
            $legacy[] = $spec->currentPriceId;
            $run->retire($spec->currentPriceId);
        }

        $addon->stripe_price_id = $priceId;
        $addon->legacy_stripe_price_ids = array_values(array_unique(array_diff($legacy, [$priceId])));
    }

    /**
     * Runs one sync, then saves whatever it changed on the plan or add-on:
     * also after a Stripe error, so a price Stripe already created is not
     * lost.
     *
     * @param  callable(StripeCatalogSyncRun): void  $sync
     */
    private function apply(BillingPlan|BillingAddon $item, callable $sync): StripeCatalogSyncData
    {
        $blocker = $this->blocker();

        if ($blocker instanceof StripeSyncBlocker) {
            return StripeCatalogSyncData::blocked($blocker);
        }

        $run = new StripeCatalogSyncRun($this->stripe());

        try {
            $sync($run);
        } catch (ApiErrorException $e) {
            report($e);
            $run->fail($e->getMessage());
        } finally {
            $this->saveChanges($item);
        }

        return $run->result();
    }

    /**
     * Runs one sync against Stripe without writing (the caller passes a copy
     * of the plan or add-on, which is never saved).
     *
     * @param  callable(StripeCatalogSyncRun): void  $sync
     */
    private function preview(callable $sync): StripeCatalogSyncData
    {
        $blocker = $this->blocker();

        if ($blocker instanceof StripeSyncBlocker) {
            return StripeCatalogSyncData::blocked($blocker);
        }

        $run = new StripeCatalogPreviewRun($this->stripe());

        try {
            $sync($run);
        } catch (ApiErrorException $e) {
            $run->fail($e->getMessage());
        }

        return $run->result();
    }

    /**
     * The product to put prices on, or null when the item sells nothing and
     * has no product yet (a contact-sales plan needs no Stripe product).
     *
     * @param  list<StripePriceSpecData>  $specs
     *
     * @throws ApiErrorException
     */
    private function productFor(StripeCatalogSyncRun $run, StripeProductSpecData $product, ?string $storedProductId, array $specs): ?string
    {
        $sells = array_any($specs, fn (StripePriceSpecData $spec): bool => $spec->isWanted());

        if (! $sells && blank($storedProductId)) {
            return null;
        }

        return $run->product($product, filled($storedProductId) ? $storedProductId : null, $this->currentProductId($run, $specs));
    }

    /**
     * The product of the item's first current price that Stripe knows.
     *
     * @param  list<StripePriceSpecData>  $specs
     *
     * @throws ApiErrorException
     */
    private function currentProductId(StripeCatalogSyncRun $run, array $specs): ?string
    {
        foreach ($specs as $spec) {
            $price = $spec->currentPriceId !== null ? $run->price($spec->currentPriceId) : null;

            if ($price instanceof Price) {
                return $price->product instanceof Product ? $price->product->id : $price->product;
            }
        }

        return null;
    }

    private function saveChanges(Model $item): void
    {
        if ($item->isDirty()) {
            $item->save();
        }
    }

    private function stripe(): StripeClient
    {
        return Cashier::stripe();
    }
}
