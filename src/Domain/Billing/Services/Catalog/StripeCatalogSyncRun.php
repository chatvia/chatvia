<?php

declare(strict_types=1);

namespace Domain\Billing\Services\Catalog;

use Domain\Billing\Data\Catalog\StripeCatalogSyncData;
use Domain\Billing\Data\Catalog\StripePriceChangeData;
use Domain\Billing\Data\Catalog\StripePriceSpecData;
use Domain\Billing\Data\Catalog\StripeProductSpecData;
use Stripe\Exception\ApiErrorException;
use Stripe\Exception\InvalidRequestException;
use Stripe\Price;
use Stripe\Product;
use Stripe\StripeClient;
use Stripe\StripeObject;

/**
 * The Stripe calls of one sync of one plan or add-on ({@see StripeCatalogSync}),
 * and the log of what they changed.
 *
 * It only ever reads, creates and updates: nothing in Stripe is deleted or
 * archived. A price is kept when Stripe says its live amount, currency and
 * billing period are what the plan or add-on asks for; otherwise a new price
 * is created. Every create carries an idempotency key made from its
 * parameters, so a sync that is repeated after a crash or a double click gets
 * the object Stripe already made instead of a second one.
 *
 * The three writes go through `send…()` methods, so {@see StripeCatalogPreviewRun}
 * can read the same live objects and log the same decisions without writing.
 */
class StripeCatalogSyncRun
{
    /** Tax behaviours a new price copies from the price it replaces. */
    private const array FIXED_TAX_BEHAVIOURS = ['inclusive', 'exclusive'];

    /** @var array<string, Price|null> live prices by id, null when Stripe does not know the id */
    private array $prices = [];

    /** @var list<string> */
    private array $createdProductIds = [];

    /** @var list<string> */
    private array $updatedProductIds = [];

    /** @var list<string> */
    private array $createdPriceIds = [];

    /** @var list<string> */
    private array $retiredPriceIds = [];

    /** @var list<StripePriceChangeData> */
    private array $priceChanges = [];

    private ?string $failure = null;

    public function __construct(
        protected readonly StripeClient $stripe,
    ) {}

    /**
     * The live price behind an id, or null when Stripe has no such price
     * (for example an id from another Stripe account).
     *
     * @throws ApiErrorException
     */
    public function price(string $priceId): ?Price
    {
        if (! array_key_exists($priceId, $this->prices)) {
            $this->prices[$priceId] = $this->findOrNull(fn (): Price => $this->stripe->prices->retrieve($priceId));
        }

        return $this->prices[$priceId];
    }

    /**
     * The product the plan or add-on sells under, updated to its name and
     * description. In order of preference: the product already stored for
     * it; the product of its current Stripe price, unless that product
     * belongs to another plan or add-on (two items that share one price must
     * not keep renaming one product); a new product.
     *
     * @throws ApiErrorException
     */
    public function product(StripeProductSpecData $spec, ?string $storedProductId, ?string $priceProductId): string
    {
        $product = $storedProductId !== null ? $this->retrieveProduct($storedProductId) : null;

        if (! $product instanceof Product && $priceProductId !== null) {
            $candidate = $this->retrieveProduct($priceProductId);
            $metadata = $candidate instanceof Product ? $this->metadata($candidate) : [];
            $unclaimed = ($metadata[StripeProductSpecData::METADATA_KEY] ?? null) === null;

            $product = $unclaimed || $spec->owns($metadata) ? $candidate : null;
        }

        if (! $product instanceof Product) {
            return $this->createProduct($spec, $storedProductId);
        }

        $this->updateProduct($product, $spec);

        return $product->id;
    }

    /**
     * The id of the price to sell with: the current price when its live
     * amount, currency and period match the spec, otherwise a new price on
     * the product.
     *
     * @throws ApiErrorException
     */
    public function priceFor(string $productId, StripePriceSpecData $spec): string
    {
        $live = $spec->currentPriceId !== null ? $this->price($spec->currentPriceId) : null;

        if ($live instanceof Price && $this->matches($live, $spec)) {
            return $live->id;
        }

        $taxBehaviour = $live?->tax_behavior;
        $params = [
            'product' => $productId,
            'currency' => strtolower($spec->currency),
            'unit_amount' => $spec->amount,
            'nickname' => $spec->nickname(),
            'metadata' => [...$spec->product->metadata(), 'chatvia_slot' => $spec->slot],
            ...($spec->recurringInterval !== null ? ['recurring' => ['interval' => $spec->recurringInterval]] : []),
            ...(in_array($taxBehaviour, self::FIXED_TAX_BEHAVIOURS, true) ? ['tax_behavior' => $taxBehaviour] : []),
        ];

        $priceId = $this->sendPriceCreate($params, $this->idempotency('price', $params, $spec->currentPriceId));
        $this->createdPriceIds[] = $priceId;
        $this->priceChanges[] = new StripePriceChangeData(
            item: $spec->product->name,
            slot: $spec->slot,
            oldPriceId: $spec->currentPriceId,
            oldAmount: $live?->unit_amount,
            oldCurrency: $live?->currency,
            newAmount: (int) $spec->amount,
            newCurrency: strtolower($spec->currency),
        );

        return $priceId;
    }

    /** Notes that a plan or add-on stopped selling with a price (it is kept as a legacy price). */
    public function retire(string $priceId): void
    {
        $this->retiredPriceIds[] = $priceId;
    }

    public function fail(string $message): void
    {
        $this->failure = $message;
    }

    public function result(): StripeCatalogSyncData
    {
        return new StripeCatalogSyncData(
            failure: $this->failure,
            createdProductIds: $this->createdProductIds,
            updatedProductIds: $this->updatedProductIds,
            createdPriceIds: $this->createdPriceIds,
            retiredPriceIds: $this->retiredPriceIds,
            priceChanges: $this->priceChanges,
        );
    }

    /**
     * Creates the product in Stripe and returns its id.
     *
     * @param  array<string, mixed>  $params
     * @param  array{idempotency_key: string}  $options
     *
     * @throws ApiErrorException
     */
    protected function sendProductCreate(array $params, array $options): string
    {
        return $this->stripe->products->create($params, $options)->id;
    }

    /**
     * @param  array<string, mixed>  $params
     *
     * @throws ApiErrorException
     */
    protected function sendProductUpdate(string $productId, array $params): void
    {
        $this->stripe->products->update($productId, $params);
    }

    /**
     * Creates the price in Stripe and returns its id.
     *
     * @param  array<string, mixed>  $params
     * @param  array{idempotency_key: string}  $options
     *
     * @throws ApiErrorException
     */
    protected function sendPriceCreate(array $params, array $options): string
    {
        $price = $this->stripe->prices->create($params, $options);
        $this->prices[$price->id] = $price;

        return $price->id;
    }

    /**
     * Whether a live price charges what the spec asks for. A price that is
     * archived in Stripe, tiered, or billed every few months does not, so it
     * is replaced.
     */
    private function matches(Price $price, StripePriceSpecData $spec): bool
    {
        $interval = $price->type === 'recurring' && (int) ($price->recurring->interval_count ?? 1) === 1
            ? $price->recurring->interval ?? null
            : null;

        return $price->active
            && $price->unit_amount === $spec->amount
            && strtolower($price->currency) === strtolower($spec->currency)
            && ($price->type === 'recurring') === ($spec->recurringInterval !== null)
            && $interval === $spec->recurringInterval;
    }

    /**
     * @throws ApiErrorException
     */
    private function retrieveProduct(string $productId): ?Product
    {
        return $this->findOrNull(fn (): Product => $this->stripe->products->retrieve($productId));
    }

    /**
     * @throws ApiErrorException
     */
    private function createProduct(StripeProductSpecData $spec, ?string $missingProductId): string
    {
        $params = [
            'name' => $spec->name,
            'metadata' => $spec->metadata(),
            ...($spec->description !== null ? ['description' => $spec->description] : []),
        ];

        // The missing id is part of the key, so a product deleted in Stripe is
        // created again rather than answered with the deleted one.
        $productId = $this->sendProductCreate($params, $this->idempotency('product', $params, $missingProductId));
        $this->createdProductIds[] = $productId;

        return $productId;
    }

    /**
     * @throws ApiErrorException
     */
    private function updateProduct(Product $product, StripeProductSpecData $spec): void
    {
        $current = $product->name === $spec->name
            && ($product->description ?? null) === $spec->description
            && $spec->owns($this->metadata($product));

        if ($current) {
            return;
        }

        $this->sendProductUpdate($product->id, [
            'name' => $spec->name,
            // An empty string removes the description in Stripe.
            'description' => $spec->description ?? '',
            'metadata' => $spec->metadata(),
        ]);
        $this->updatedProductIds[] = $product->id;
    }

    /**
     * A product's metadata. Stripe sends empty metadata as a plain empty
     * array rather than an object.
     *
     * @return array<string, mixed>
     */
    private function metadata(Product $product): array
    {
        $metadata = $product->metadata;

        return $metadata instanceof StripeObject ? $metadata->toArray() : (array) $metadata;
    }

    /**
     * Runs a retrieve, answering null when Stripe does not know the id.
     *
     * @template TObject
     *
     * @param  callable(): TObject  $retrieve
     * @return TObject|null
     *
     * @throws ApiErrorException
     */
    private function findOrNull(callable $retrieve): mixed
    {
        try {
            return $retrieve();
        } catch (InvalidRequestException $e) {
            if ($e->getStripeCode() === 'resource_missing') {
                return null;
            }

            throw $e;
        }
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array{idempotency_key: string}
     */
    private function idempotency(string $object, array $params, ?string $replacing): array
    {
        return ['idempotency_key' => "chatvia-{$object}-".hash('sha256', (string) json_encode([$params, $replacing]))];
    }
}
