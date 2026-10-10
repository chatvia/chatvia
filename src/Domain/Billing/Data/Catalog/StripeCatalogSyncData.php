<?php

declare(strict_types=1);

namespace Domain\Billing\Data\Catalog;

use Domain\Billing\Enums\StripeSyncBlocker;
use Domain\Billing\Services\Catalog\StripeCatalogSync;
use Spatie\LaravelData\Data;

/**
 * What one sync with Stripe did ({@see StripeCatalogSync}), or what a preview
 * found it would do: the products and prices it created, the products it
 * renamed, each new price with the amount it replaces (`priceChanges`), and
 * the prices a plan or add-on stopped using (kept as its legacy prices, so
 * existing subscribers keep them). `blocker` says why nothing was sent;
 * `failure` holds Stripe's answer when a call failed part-way (what was
 * created before it is kept).
 */
class StripeCatalogSyncData extends Data
{
    /**
     * @param  list<string>  $createdProductIds
     * @param  list<string>  $updatedProductIds
     * @param  list<string>  $createdPriceIds
     * @param  list<string>  $retiredPriceIds
     * @param  list<StripePriceChangeData>  $priceChanges
     */
    public function __construct(
        public ?StripeSyncBlocker $blocker = null,
        public ?string $failure = null,
        public array $createdProductIds = [],
        public array $updatedProductIds = [],
        public array $createdPriceIds = [],
        public array $retiredPriceIds = [],
        public array $priceChanges = [],
    ) {}

    public static function blocked(StripeSyncBlocker $blocker): self
    {
        return new self(blocker: $blocker);
    }

    public function wasSent(): bool
    {
        return ! $this->blocker instanceof StripeSyncBlocker;
    }

    public function failed(): bool
    {
        return $this->failure !== null;
    }

    /** Whether a plan or add-on now sells at a new price while its subscribers keep the old one. */
    public function replacedPrices(): bool
    {
        return $this->retiredPriceIds !== [];
    }

    public function changedStripe(): bool
    {
        return $this->createdProductIds !== []
            || $this->updatedProductIds !== []
            || $this->createdPriceIds !== []
            || $this->retiredPriceIds !== [];
    }

    /** Both results together, as one sync of several plans and add-ons. */
    public function merge(self $other): self
    {
        return new self(
            blocker: $this->blocker ?? $other->blocker,
            failure: $this->failure ?? $other->failure,
            createdProductIds: [...$this->createdProductIds, ...$other->createdProductIds],
            updatedProductIds: [...$this->updatedProductIds, ...$other->updatedProductIds],
            createdPriceIds: [...$this->createdPriceIds, ...$other->createdPriceIds],
            retiredPriceIds: [...$this->retiredPriceIds, ...$other->retiredPriceIds],
            priceChanges: [...$this->priceChanges, ...$other->priceChanges],
        );
    }
}
