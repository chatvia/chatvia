<?php

declare(strict_types=1);

namespace Support\Radar;

/**
 * The keys of the services that are not AI providers (an AI provider's key
 * is its own value, such as "fal"). Each has an entry in config/radar.php.
 * Stored, so a key must not change.
 */
final class ServiceKey
{
    public const string FIRECRAWL = 'firecrawl';

    public const string IP2LOCATION = 'ip2location';

    public const string MAIL = 'mail';

    public const string BACKUPS = 'backups';

    public const string LICENSE = 'license';

    public const string QUEUE = 'queue';

    public const string SCHEDULER = 'scheduler';

    public const string DATABASE = 'database';

    public const string REDIS = 'redis';

    public const string REALTIME = 'realtime';

    public const string STORAGE = 'storage';

    public const string WORKSPACES = 'workspaces';
}
