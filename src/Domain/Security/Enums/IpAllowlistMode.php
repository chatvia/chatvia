<?php

declare(strict_types=1);

namespace Domain\Security\Enums;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Whether the workspace's network access control is on. It is the master
 * switch of the network card: Off turns the IP allowlist off; Dashboard
 * enforces it on the dashboard. DashboardApi (dashboard + public API) is
 * reserved and not selectable yet. Stored in
 * `workspace_security_policies.ip_allowlist_mode`.
 */
#[TypeScript]
enum IpAllowlistMode: string
{
    case Off = 'off';
    case Dashboard = 'dashboard';
    case DashboardApi = 'dashboard_api';

    /**
     * The modes the settings form may save.
     *
     * @return list<string>
     */
    public static function selectable(): array
    {
        return [self::Off->value, self::Dashboard->value];
    }
}
