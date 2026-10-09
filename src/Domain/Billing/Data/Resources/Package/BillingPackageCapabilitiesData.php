<?php

namespace Domain\Billing\Data\Resources\Package;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * The features a plan includes and how long it keeps conversations. Built from
 * the plan config, or all-on when billing is turned off.
 */
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
        // Agent governance suite. Eval suites (answer-quality testing) and
        // AI-safety guardrails ship from Standard up; the per-agent tool-call
        // audit log is Pro (and Enterprise). Enforced in the UI via the
        // LockedFeature card and on mutation routes via `capability:` middleware.
        public bool $evals = false,
        public bool $guardrails = false,
        public bool $audit = false,
        public bool $removeBranding = false,
        // The Security & Compliance suite (network access control, configurable
        // retention, security audit log). Single umbrella flag — Pro and
        // Enterprise tiers.
        public bool $security = false,
        // SIEM webhook delivery — its own capability, decoupled from the
        // `security` flag so it can be granted independently. Pro (and
        // Enterprise, which is Pro + manual grants).
        public bool $siemWebhook = false,
        // Days a conversation (and its cached memory) lives before the
        // scheduled prune deletes it. Set per plan in config/billing.php;
        // ChatCache reads it for the Redis TTL and the conversation create
        // path stamps `chat_conversations.expires_at` from it.
        public int $retentionDays = self::DEFAULT_RETENTION_DAYS,
    ) {}

    /**
     * The features a plan turns on, read from the plan's `capabilities` entry
     * in config/billing.php. An unknown plan gets the safe default: every
     * feature off and the Starter retention floor.
     */
    public static function forPlan(string $plan): self
    {
        $packages = (array) config('billing.packages', []);
        $capabilities = $packages[strtolower($plan)]['capabilities'] ?? null;

        return is_array($capabilities) ? self::fromArray($capabilities) : new self;
    }

    /**
     * Every feature on. Used when billing is turned off (a self-hosted
     * install), where there are no plans to hold features back.
     */
    public static function unlimited(int $retentionDays): self
    {
        return new self(
            sentiment: true,
            tone: true,
            topics: true,
            handoff: true,
            api: true,
            pulse: true,
            evals: true,
            guardrails: true,
            audit: true,
            removeBranding: true,
            security: true,
            siemWebhook: true,
            retentionDays: $retentionDays,
        );
    }

    /**
     * @param  array<string, bool|int>  $capabilities  a plan's `capabilities` config: feature flags plus `retentionDays`
     */
    public static function fromArray(array $capabilities): self
    {
        return new self(
            sentiment: $capabilities['sentiment'] ?? false,
            tone: $capabilities['tone'] ?? false,
            topics: $capabilities['topics'] ?? false,
            handoff: $capabilities['handoff'] ?? false,
            api: $capabilities['api'] ?? false,
            pulse: $capabilities['pulse'] ?? false,
            evals: $capabilities['evals'] ?? false,
            guardrails: $capabilities['guardrails'] ?? false,
            audit: $capabilities['audit'] ?? false,
            removeBranding: $capabilities['removeBranding'] ?? false,
            security: $capabilities['security'] ?? false,
            siemWebhook: $capabilities['siemWebhook'] ?? false,
            retentionDays: $capabilities['retentionDays'] ?? self::DEFAULT_RETENTION_DAYS,
        );
    }
}
