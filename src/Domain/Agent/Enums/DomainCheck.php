<?php

declare(strict_types=1);

namespace Domain\Agent\Enums;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * What the last check of an agent's custom domain found. Stored on
 * `agent_domains.last_check`, so never rename a value; the Deploy page
 * explains each one.
 */
#[TypeScript]
enum DomainCheck: string
{
    /** The domain reached this install over HTTPS. */
    case Reached = 'reached';

    /** The domain reached this install, but only over plain HTTP. */
    case NoCertificate = 'no_certificate';

    /** The domain answered, but not from this install. */
    case PointsElsewhere = 'points_elsewhere';

    /** Nothing answered: no DNS record yet, or a server that is down. */
    case NotReached = 'not_reached';

    /** The domain points to a private network address. */
    case PrivateAddress = 'private_address';
}
