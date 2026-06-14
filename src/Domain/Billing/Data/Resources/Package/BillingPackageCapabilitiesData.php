<?php

namespace Domain\Billing\Data\Resources\Package;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class BillingPackageCapabilitiesData extends Data
{
    /**
     * Conversation retention used when the plan is unknown or unresolvable.
     * Matches the Starter floor so a downgrade / billing glitch never wipes
     * data shorter than what the user originally paid for.
     */
    public const DEFAULT_RETENTION_DAYS = 90;

    public function __construct(
        public bool $sentiment = false,
        public bool $tone = false,
        public bool $topics = false,
        public bool $handoff = false,
        public bool $api = false,
        public bool $pulse = false,
        public bool $removeBranding = false,
        // The Security & Compliance suite (network access control, configurable
        // retention, security audit log). Single umbrella flag — Ultra and
        // Enterprise tiers.
        public bool $security = false,
        // SIEM webhook delivery — its own capability, decoupled from the
        // `security` flag so it can be granted independently. Ultra (and
        // Enterprise, which is Ultra + manual grants).
        public bool $siemWebhook = false,
        // Days a conversation (and its cached memory) lives before the
        // scheduled prune deletes it. Set per plan in the factory methods
        // below; ChatCache reads it for the Redis TTL and the conversation
        // create path stamps `chat_conversations.expires_at` from it.
        public int $retentionDays = self::DEFAULT_RETENTION_DAYS,
    ) {}

    /*
    |--------------------------------------------------------------------------
    | Plan Factory Methods
    |--------------------------------------------------------------------------
    */

    public static function starter(): self
    {
        return new self(
            retentionDays: 90,
        );
    }

    public static function standard(): self
    {
        return new self(
            sentiment: true,
            handoff: true,
            api: true,
            retentionDays: 180,
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
            removeBranding: true,
            security: true,
            siemWebhook: true,
            retentionDays: 366,
        );
    }

    public static function enterprise(): self
    {
        return new self(
            sentiment: true,
            tone: true,
            topics: true,
            handoff: true,
            api: true,
            pulse: true,
            removeBranding: true,
            security: true,
            siemWebhook: true,
            retentionDays: 366,
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
            'enterprise' => self::enterprise(),
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
            removeBranding: $capabilities['removeBranding'] ?? false,
            security: $capabilities['security'] ?? false,
            siemWebhook: $capabilities['siemWebhook'] ?? false,
            retentionDays: $capabilities['retentionDays'] ?? self::DEFAULT_RETENTION_DAYS,
        );
    }
}
