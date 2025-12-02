<?php

namespace App\Enums;

use App\Data\Resources\Billing\BillingPackageCapabilitiesData;

enum CapabilityEnum: string
{
    case CUSTOM_SUB_DOMAIN = 'custom_sub_domain';
    case CUSTOM_DOMAINS = 'custom_domains';
    case ADVANCED_PDF = 'advanced_pdf';
    case OCR = 'ocr';
    case AUDIO = 'audio';
    case VIDEO = 'video';
    case API_ACCESS = 'api_access';
    case CUSTOM_INTEGRATIONS = 'custom_integrations';
    case TEAM_COLLABORATION = 'team_collaboration';

    /**
     * Get capabilities for a specific plan as an array
     */
    public static function forPlan(string $plan): array
    {
        return BillingPackageCapabilitiesData::forPlan($plan)->toArray();
    }

    /**
     * Get the capability DTO for a specific plan
     */
    public static function dtoForPlan(string $plan): BillingPackageCapabilitiesData
    {
        return BillingPackageCapabilitiesData::forPlan($plan);
    }

    /**
     * Get a human-readable label for this capability
     */
    public function label(): string
    {
        return match ($this) {
            self::CUSTOM_SUB_DOMAIN => 'Custom Subdomain',
            self::CUSTOM_DOMAINS => 'Custom Domains',
            self::ADVANCED_PDF => 'Advanced PDF Processing',
            self::OCR => 'OCR Support',
            self::AUDIO => 'Audio Processing',
            self::VIDEO => 'Video Processing',
            self::API_ACCESS => 'API Access',
            self::CUSTOM_INTEGRATIONS => 'Custom Integrations',
            self::TEAM_COLLABORATION => 'Team Collaboration',
        };
    }
}
