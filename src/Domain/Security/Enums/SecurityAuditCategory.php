<?php

namespace Domain\Security\Enums;

/**
 * Coarse grouping for {@see SecurityAuditAction}, used by the audit-viewer
 * filter chips and the Compliance API category facet.
 */
enum SecurityAuditCategory: string
{
    case Auth = 'auth';
    case Membership = 'membership';
    case Config = 'config';
    case Data = 'data';

    public function label(): string
    {
        return match ($this) {
            self::Auth => 'Authentication',
            self::Membership => 'Membership & roles',
            self::Config => 'Configuration',
            self::Data => 'Data',
        };
    }
}
