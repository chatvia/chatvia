<?php

namespace Domain\Security\Enums;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * The workspace's country rule: Off, Allow (only the listed countries may
 * reach the dashboard) or Deny (the listed countries may not). Stored in
 * `workspace_security_policies.geo_mode`.
 */
#[TypeScript]
enum GeoMode: string
{
    case Off = 'off';
    case Allow = 'allow';
    case Deny = 'deny';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
