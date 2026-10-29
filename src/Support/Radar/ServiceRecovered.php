<?php

declare(strict_types=1);

namespace Support\Radar;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * A service works again (a call went through, an email was sent, a backup
 * finished), so Radar closes its open issues that close by themselves. With
 * a subject (a job class, a command, a workspace id) only that subject's
 * issues close; without one, only the service's own. With a type, only
 * issues of that kind close (a raised message limit leaves a reached
 * generation limit open).
 */
final readonly class ServiceRecovered
{
    use Dispatchable;

    public function __construct(
        public string $service,
        public ?string $subject = null,
        public ?string $type = null,
    ) {}
}
