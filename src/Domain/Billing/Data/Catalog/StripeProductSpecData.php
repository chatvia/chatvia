<?php

declare(strict_types=1);

namespace Domain\Billing\Data\Catalog;

use Domain\Billing\Models\BillingAddon;
use Domain\Billing\Models\BillingPlan;

/**
 * The Stripe product a plan or add-on should have: its name, description and
 * the metadata that marks it as Chatvia's (`chatvia_kind` + `chatvia_key`).
 * The metadata is how the sync recognises its own products, for example to
 * tell whether an existing product already belongs to another plan.
 */
final readonly class StripeProductSpecData
{
    public const string METADATA_KIND = 'chatvia_kind';

    public const string METADATA_KEY = 'chatvia_key';

    public const string KIND_PLAN = 'plan';

    public const string KIND_ADDON = 'addon';

    public function __construct(
        public string $kind,
        public string $key,
        public string $name,
        public ?string $description,
    ) {}

    public static function forPlan(BillingPlan $plan): self
    {
        return new self(self::KIND_PLAN, $plan->key, $plan->name, self::text($plan->description));
    }

    public static function forAddon(BillingAddon $addon): self
    {
        return new self(self::KIND_ADDON, $addon->key, $addon->name, self::text($addon->description));
    }

    /**
     * @return array{chatvia_kind: string, chatvia_key: string}
     */
    public function metadata(): array
    {
        return [self::METADATA_KIND => $this->kind, self::METADATA_KEY => $this->key];
    }

    /**
     * Whether Stripe metadata marks an object as this plan's or add-on's.
     *
     * @param  array<string, mixed>  $metadata
     */
    public function owns(array $metadata): bool
    {
        return ($metadata[self::METADATA_KIND] ?? null) === $this->kind
            && ($metadata[self::METADATA_KEY] ?? null) === $this->key;
    }

    private static function text(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value !== '' ? $value : null;
    }
}
