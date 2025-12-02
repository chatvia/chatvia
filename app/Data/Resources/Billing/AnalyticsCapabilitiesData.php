<?php

namespace App\Data\Resources\Billing;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class AnalyticsCapabilitiesData extends Data
{
    public function __construct(
        public bool $topics = false,
        public bool $sentiment = false,
        public bool $tone = false,
        public bool $metrics = false,
        public bool $sources = false,
    ) {}

    public static function none(): self
    {
        return new self;
    }

    public static function basic(): self
    {
        return new self(
            metrics: true,
            sources: true,
        );
    }

    public static function advanced(): self
    {
        return new self(
            topics: true,
            sentiment: true,
            tone: false,
            metrics: true,
            sources: true,
        );
    }

    public static function full(): self
    {
        return new self(
            topics: true,
            sentiment: true,
            tone: true,
            metrics: true,
            sources: true,
        );
    }
}
