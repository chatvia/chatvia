<?php

declare(strict_types=1);

namespace Support\License;

use Illuminate\Contracts\Config\Repository as Config;

/**
 * What this install's license key unlocks, for code that may not reach the
 * admin settings (the domain layer, middleware). It reads the flags that
 * SettingsConfig works out from the saved key, so a removed or lapsed key
 * locks the features again on the next request or job.
 *
 * Without any valid key the {@see FreeTier} limits apply; a limit method
 * returns null when there is none.
 */
final readonly class Entitlements
{
    public function __construct(private Config $config) {}

    /** Whether the install holds a valid license key of any kind. */
    public function licensed(): bool
    {
        return $this->config->get('chatvia.license.licensed') === true;
    }

    /** Whether the Enterprise security features are unlocked. */
    public function enterprise(): bool
    {
        return $this->config->get('chatvia.license.enterprise') === true;
    }

    public function workspaceLimit(): ?int
    {
        return $this->licensed() ? null : FreeTier::WORKSPACES;
    }

    public function agentLimit(): ?int
    {
        return $this->licensed() ? null : FreeTier::AGENTS;
    }

    /** Teams a workspace may hold; without a key only the one it starts with. */
    public function teamLimit(): ?int
    {
        return $this->licensed() ? null : FreeTier::TEAMS;
    }

    /** The monthly cap on a workspace's agent replies, on top of any limit an admin set. */
    public function monthlyMessageLimit(): ?int
    {
        return $this->licensed() ? null : FreeTier::MONTHLY_MESSAGES;
    }

    /** Whether the public API answers. */
    public function api(): bool
    {
        return $this->licensed();
    }

    /** Whether voice conversations may be offered. */
    public function voice(): bool
    {
        return $this->licensed();
    }

    /**
     * Whether agents may use their capabilities (charts, calendar booking,
     * forms and leads, image, audio and video generation).
     */
    public function capabilities(): bool
    {
        return $this->licensed();
    }

    /** Whether the Splitting options apply; without a key texts are split with the defaults. */
    public function customSplitting(): bool
    {
        return $this->licensed();
    }
}
