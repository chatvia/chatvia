<?php

namespace Domain\Billing\Enums;

use Domain\Billing\Data\Resources\Package\BillingPackageCapabilitiesData;

enum CapabilityEnum: string
{
    case SENTIMENT = 'sentiment';
    case TONE = 'tone';
    case TOPICS = 'topics';
    case HANDOFF = 'handoff';
    case API = 'api';
    case PULSE = 'pulse';

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
            self::SENTIMENT => 'Sentiment Analysis',
            self::TONE => 'Tone Analysis',
            self::TOPICS => 'Topic Analysis',
            self::HANDOFF => 'Handoff Support',
            self::API => 'API Access',
            self::PULSE => 'Conversation Pulse',
        };
    }
}
