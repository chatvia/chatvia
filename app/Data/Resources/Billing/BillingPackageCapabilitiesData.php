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
        public bool $priority_support = false,
    ) {}

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
            priority_support: $capabilities['priority_support'] ?? false,
        );
    }
}
