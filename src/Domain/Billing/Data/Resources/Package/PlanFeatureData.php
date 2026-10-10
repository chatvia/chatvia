<?php

declare(strict_types=1);

namespace Domain\Billing\Data\Resources\Package;

use Domain\Billing\Enums\PlanFeature;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * One feature bullet of a plan card. Either a generated bullet (`key`, shown
 * with the vue-i18n message `billing.features.<key>` and its `params`, for
 * example `count`), or a free-text line the admin typed on the plan (`text`),
 * shown as typed.
 */
#[TypeScript]
final class PlanFeatureData extends Data
{
    /**
     * @param  array<string, int|string>  $params
     */
    public function __construct(
        public ?PlanFeature $key = null,
        public array $params = [],
        public ?string $text = null,
    ) {}

    /**
     * @param  array<string, int|string>  $params
     */
    public static function of(PlanFeature $key, array $params = []): self
    {
        return new self(key: $key, params: $params);
    }

    public static function freeText(string $text): self
    {
        return new self(text: $text);
    }
}
