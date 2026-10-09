<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Default Store
    |--------------------------------------------------------------------------
    |
    | Feature flags in this app are ENTITLEMENT-derived: each feature's resolver
    | reads the workspace's live plan capabilities, exactly like the rest of the
    | billing layer (which never persists entitlements — it recomputes them per
    | request). The `array` store keeps that model: the resolver runs each
    | request, so a flag always reflects the workspace's CURRENT plan with no
    | stale-cache to purge on upgrade/downgrade.
    |
    | Persistent per-workspace admin grants (staff flipping the suite on/off for
    | ANY workspace, à-la-carte) do NOT use Pennant's own store — they live in
    | `WorkspaceSecurityPolicy.security_override`, which the resolver reads before
    | falling back to the plan. Keeping overrides in our own table (rather than
    | the database store) is what lets us stay on the array store and avoid the
    | plan-change staleness/purge problem entirely.
    |
    */

    'default' => env('PENNANT_STORE', 'array'),

    /*
    |--------------------------------------------------------------------------
    | Pennant Stores
    |--------------------------------------------------------------------------
    */

    'stores' => [

        'array' => [
            'driver' => 'array',
        ],

        'database' => [
            'driver' => 'database',
            'connection' => env('PENNANT_DB_CONNECTION'),
            'table' => 'features',
        ],

    ],

];
