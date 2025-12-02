<?php

namespace App\Data\Resources\Billing;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class BillingPackageCapabilitiesData extends Data
{
    public function __construct(
        public bool $custom_sub_domain = false,
        public bool $custom_domains = false,
        public bool $advanced_pdf = false,
        public bool $ocr = false,
        public bool $audio = false,
        public bool $video = false,
        public bool $api_access = false,
        public bool $custom_integrations = false,
        public bool $team_collaboration = false,
        public AnalyticsCapabilitiesData $analytics = new AnalyticsCapabilitiesData,
    ) {}

    /*
    |--------------------------------------------------------------------------
    | Plan Factory Methods
    |--------------------------------------------------------------------------
    */

    public static function starter(): self
    {
        return new self(
            custom_sub_domain: true,
            audio: true,
            video: true,
            analytics: AnalyticsCapabilitiesData::basic(),
        );
    }

    public static function standard(): self
    {
        return new self(
            custom_sub_domain: true,
            advanced_pdf: true,
            ocr: true,
            team_collaboration: true,
            analytics: new AnalyticsCapabilitiesData(
                sentiment: true,
                metrics: true,
                sources: true,
            ),
        );
    }

    public static function pro(): self
    {
        return new self(
            custom_sub_domain: true,
            custom_domains: true,
            advanced_pdf: true,
            ocr: true,
            audio: true,
            video: true,
            api_access: true,
            custom_integrations: true,
            team_collaboration: true,
            analytics: AnalyticsCapabilitiesData::full(),
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
            custom_sub_domain: $capabilities['custom_sub_domain'] ?? false,
            custom_domains: $capabilities['custom_domains'] ?? false,
            advanced_pdf: $capabilities['advanced_pdf'] ?? false,
            ocr: $capabilities['ocr'] ?? false,
            audio: $capabilities['audio'] ?? false,
            video: $capabilities['video'] ?? false,
            api_access: $capabilities['api_access'] ?? false,
            custom_integrations: $capabilities['custom_integrations'] ?? false,
            team_collaboration: $capabilities['team_collaboration'] ?? false,
            analytics: isset($capabilities['analytics'])
                ? AnalyticsCapabilitiesData::from($capabilities['analytics'])
                : new AnalyticsCapabilitiesData,
        );
    }
}
