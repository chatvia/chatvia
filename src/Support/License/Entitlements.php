<?php

declare(strict_types=1);

namespace Support\License;

use Illuminate\Contracts\Config\Repository as Config;

/**
 * What this install's license key unlocks, for code that may not reach the
 * admin settings (the domain layer, middleware). It reads the flags that
 * SettingsConfig works out from the saved key, so a removed or lapsed key
 * locks the features again on the next request or job.
 */
final readonly class Entitlements
{
    public function __construct(private Config $config) {}

    /** Whether the Enterprise security features are unlocked. */
    public function enterprise(): bool
    {
        return $this->config->get('chatvia.license.enterprise') === true;
    }
}
