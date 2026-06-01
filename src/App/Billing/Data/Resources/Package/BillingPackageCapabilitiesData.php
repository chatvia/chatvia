<?php

namespace App\Billing\Data\Resources\Package;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class BillingPackageCapabilitiesData extends Data
{
    public function __construct(
        public bool $sentiment = false,
        public bool $tone = false,
        public bool $topics = false,
        public bool $handoff = false,
        public bool $api = false,
        public bool $pulse = false,
    ) {}

    /*
    |--------------------------------------------------------------------------
    | Plan Factory Methods
    |--------------------------------------------------------------------------
    */

    public static function starter(): self
    {
        return new self;
    }

    public static function standard(): self
    {
        return new self(
            sentiment: true,
        );
    }

    public static function pro(): self
    {
        return new self(
            sentiment: true,
            tone: true,
            topics: true,
            handoff: true,
            api: true,
            pulse: true,
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Helper Methods
    |--------------------------------------------------------------------------
    */

    public static function forPlan(string $plan): self
    {
        return match (strtolower($plan)) {
            'starter' => self::starter(),
            'standard' => self::standard(),
            'pro' => self::pro(),
            default => new self,
        };
    }

    public static function fromArray(array $capabilities): self
    {
        return new self(
            sentiment: $capabilities['sentiment'] ?? false,
            tone: $capabilities['tone'] ?? false,
            topics: $capabilities['topics'] ?? false,
            handoff: $capabilities['handoff'] ?? false,
            api: $capabilities['api'] ?? false,
            pulse: $capabilities['pulse'] ?? false,
        );
    }
}
