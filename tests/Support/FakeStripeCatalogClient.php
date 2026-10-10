<?php

declare(strict_types=1);

namespace Tests\Support;

use Domain\Billing\Enums\Addon;
use Domain\Billing\Enums\MessageCreditAddon;
use Domain\Billing\Models\BillingAddon;
use Domain\Billing\Models\BillingPlan;
use Stripe\Exception\ApiConnectionException;
use Stripe\Exception\InvalidRequestException;
use Stripe\Price;
use Stripe\Product;
use Stripe\StripeClient;
use Stripe\StripeObject;

/**
 * An in-memory Stripe for the product and price calls of the catalog sync.
 * `bind()` puts it in the container, where `Cashier::stripe()` finds it.
 *
 * Products and prices live in arrays; an unknown id answers like Stripe
 * (`resource_missing`). A create with an idempotency key that was used before
 * returns the object made the first time, as Stripe does. Every create and
 * update is recorded, so a test can assert exactly what was sent.
 */
final class FakeStripeCatalogClient extends StripeClient
{
    /** @var object products service: retrieve / create / update */
    public object $products;

    /** @var object prices service: retrieve / create */
    public object $prices;

    /** @var array<string, Product> */
    public array $productStore = [];

    /** @var array<string, Price> */
    public array $priceStore = [];

    /** @var list<array<string, mixed>> params of every product create */
    public array $createdProducts = [];

    /** @var list<array{0: string, 1: array<string, mixed>}> id and params of every product update */
    public array $updatedProducts = [];

    /** @var list<array<string, mixed>> params of every price create */
    public array $createdPrices = [];

    /** A price create with this amount fails like a lost connection. */
    public ?int $failPriceCreateFor = null;

    /** @var array<string, StripeObject> objects by idempotency key */
    private array $idempotent = [];

    private int $sequence = 0;

    public function __construct()
    {
        parent::__construct(['api_key' => 'sk_test_fake_catalog']);

        $this->products = new readonly class($this)
        {
            public function __construct(private FakeStripeCatalogClient $stripe) {}

            /** @param  array<string, mixed>|null  $params */
            public function retrieve(string $id, ?array $params = null, mixed $opts = null): Product
            {
                return $this->stripe->productStore[$id] ?? throw FakeStripeCatalogClient::missing($id);
            }

            /** @param  array<string, mixed>  $params */
            public function create(array $params, mixed $opts = null): Product
            {
                return $this->stripe->createProduct($params, $opts['idempotency_key'] ?? null);
            }

            /** @param  array<string, mixed>  $params */
            public function update(string $id, array $params, mixed $opts = null): Product
            {
                return $this->stripe->updateProduct($id, $params);
            }
        };

        $this->prices = new readonly class($this)
        {
            public function __construct(private FakeStripeCatalogClient $stripe) {}

            /** @param  array<string, mixed>|null  $params */
            public function retrieve(string $id, ?array $params = null, mixed $opts = null): Price
            {
                return $this->stripe->priceStore[$id] ?? throw FakeStripeCatalogClient::missing($id);
            }

            /** @param  array<string, mixed>  $params */
            public function create(array $params, mixed $opts = null): Price
            {
                return $this->stripe->createPrice($params, $opts['idempotency_key'] ?? null);
            }
        };
    }

    public static function bind(): self
    {
        $stripe = new self;
        app()->bind(StripeClient::class, fn (): self => $stripe);

        return $stripe;
    }

    public static function missing(string $id): InvalidRequestException
    {
        return InvalidRequestException::factory("No such object: '{$id}'", 404, null, null, null, 'resource_missing');
    }

    /**
     * A product that already exists in Stripe.
     *
     * @param  array<string, string>  $metadata
     */
    public function seedProduct(string $id, string $name, array $metadata = [], ?string $description = null): Product
    {
        return $this->productStore[$id] = Product::constructFrom([
            'id' => $id,
            'object' => 'product',
            'active' => true,
            'name' => $name,
            'description' => $description,
            'metadata' => $metadata,
        ]);
    }

    /**
     * A price that already exists in Stripe, e.g. a recurring price with
     * `['interval' => 'month']` or a one-time price with `null`.
     *
     * @param  array<string, mixed>|null  $recurring
     * @param  array<string, mixed>  $overrides
     */
    public function seedPrice(string $id, string $productId, int $amount, ?array $recurring, string $currency = 'eur', array $overrides = []): Price
    {
        return $this->priceStore[$id] = Price::constructFrom([
            'id' => $id,
            'object' => 'price',
            'active' => true,
            'product' => $productId,
            'currency' => $currency,
            'unit_amount' => $amount,
            'billing_scheme' => 'per_unit',
            'type' => $recurring === null ? 'one_time' : 'recurring',
            'recurring' => $recurring === null ? null : ['interval_count' => 1, 'usage_type' => 'licensed', ...$recurring],
            'tax_behavior' => 'unspecified',
            'metadata' => [],
            ...$overrides,
        ]);
    }

    /**
     * Makes this Stripe look like production's: every migrated plan and
     * add-on has a live product (named in Stripe, without Chatvia metadata)
     * and live prices that charge exactly the saved amounts, and the two
     * credit packs share one one-time price of `$creditPackAmount`. The rows
     * get these ids and no product id, as after the migration.
     */
    public function seedLiveCatalog(int $creditPackAmount): void
    {
        foreach (BillingPlan::query()->where('contact_sales', false)->get() as $plan) {
            $this->seedProduct("prod_{$plan->key}", "Chatvia {$plan->name}");
            $this->seedPrice("price_{$plan->key}_m", "prod_{$plan->key}", $plan->monthly_price_cents, ['interval' => 'month'], $plan->currency);
            $this->seedPrice("price_{$plan->key}_y", "prod_{$plan->key}", (int) $plan->yearly_price_cents, ['interval' => 'year'], $plan->currency);
            $plan->update(['stripe_product_id' => null, 'stripe_monthly_price_id' => "price_{$plan->key}_m", 'stripe_yearly_price_id' => "price_{$plan->key}_y"]);
        }

        foreach (Addon::cases() as $case) {
            $addon = BillingAddon::query()->where('key', $case->value)->firstOrFail();
            $this->seedProduct("prod_{$addon->key}", $addon->name);
            $this->seedPrice("price_{$addon->key}", "prod_{$addon->key}", $addon->price_cents, ['interval' => 'month'], $addon->currency);
            $addon->update(['stripe_product_id' => null, 'stripe_price_id' => "price_{$addon->key}"]);
        }

        $this->seedProduct('prod_credits', '1,000 extra messages');
        $this->seedPrice('price_credits', 'prod_credits', $creditPackAmount, null);

        foreach (MessageCreditAddon::cases() as $case) {
            BillingAddon::query()->where('key', $case->value)->firstOrFail()
                ->update(['stripe_product_id' => null, 'stripe_price_id' => 'price_credits']);
        }
    }

    /** Every create and update sent, for "nothing happened" assertions. */
    public function writes(): int
    {
        return count($this->createdProducts) + count($this->updatedProducts) + count($this->createdPrices);
    }

    /**
     * @param  array<string, mixed>  $params
     */
    public function createProduct(array $params, ?string $idempotencyKey): Product
    {
        if ($idempotencyKey !== null && isset($this->idempotent[$idempotencyKey])) {
            return $this->idempotent[$idempotencyKey];
        }

        $this->createdProducts[] = $params;
        $product = $this->seedProduct('prod_fake_'.++$this->sequence, $params['name'], $params['metadata'] ?? [], $params['description'] ?? null);

        return $this->remember($idempotencyKey, $product);
    }

    /**
     * @param  array<string, mixed>  $params
     */
    public function updateProduct(string $id, array $params): Product
    {
        $current = $this->productStore[$id] ?? throw self::missing($id);
        $this->updatedProducts[] = [$id, $params];

        return $this->seedProduct(
            $id,
            $params['name'] ?? $current->name,
            [...($current->metadata instanceof StripeObject ? $current->metadata->toArray() : []), ...($params['metadata'] ?? [])],
            array_key_exists('description', $params) ? ($params['description'] === '' ? null : $params['description']) : $current->description,
        );
    }

    /**
     * @param  array<string, mixed>  $params
     */
    public function createPrice(array $params, ?string $idempotencyKey): Price
    {
        if ($idempotencyKey !== null && isset($this->idempotent[$idempotencyKey])) {
            return $this->idempotent[$idempotencyKey];
        }

        if ($params['unit_amount'] === $this->failPriceCreateFor) {
            throw ApiConnectionException::factory('Could not connect to Stripe.');
        }

        $this->createdPrices[] = $params;
        $price = $this->seedPrice(
            'price_fake_'.++$this->sequence,
            $params['product'],
            $params['unit_amount'],
            $params['recurring'] ?? null,
            $params['currency'],
            ['metadata' => $params['metadata'] ?? [], 'nickname' => $params['nickname'] ?? null, 'tax_behavior' => $params['tax_behavior'] ?? 'unspecified'],
        );

        return $this->remember($idempotencyKey, $price);
    }

    /**
     * @template TObject of StripeObject
     *
     * @param  TObject  $object
     * @return TObject
     */
    private function remember(?string $idempotencyKey, StripeObject $object): StripeObject
    {
        if ($idempotencyKey !== null) {
            $this->idempotent[$idempotencyKey] = $object;
        }

        return $object;
    }
}
