<?php

declare(strict_types=1);

namespace Domain\Security\Enums;

/**
 * Laravel Pennant feature flags for the Enterprise Security & Compliance suite.
 * Gated via the `features:` middleware (e.g. `features:security`) and checked in
 * PHP with `Feature::for($scope)->active(SecurityFeature::Security->value)`.
 *
 * Each flag is ENTITLEMENT-derived: its resolver (registered in AppServiceProvider)
 * reads the workspace's live plan capabilities, so it is active for ANY workspace
 * whose plan includes the feature — not hardcoded to a tier name. Today the suite
 * ships behind one umbrella flag; splitting into à-la-carte flags later is just
 * adding cases here plus their resolvers.
 */
enum SecurityFeature: string
{
    case Security = 'security';

    public function label(): string
    {
        return match ($this) {
            self::Security => 'Enterprise Security & Compliance',
        };
    }
}
