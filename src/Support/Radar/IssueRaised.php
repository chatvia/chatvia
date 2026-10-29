<?php

declare(strict_types=1);

namespace Support\Radar;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * Something an admin must fix happened: a service refused the key, has no
 * key, ran out of credit, a backup failed… Any code may raise it, without
 * knowing about the admin panel; Radar (the admin panel's issues page)
 * records it. `service` is a {@see ServiceKey} or an AI provider's key,
 * `type` an {@see IssueTypeKey}. `subject` is what a per-subject kind is
 * about (a job class, a command, a workspace id); a gone model's subject
 * is its model.
 */
final readonly class IssueRaised
{
    use Dispatchable;

    public function __construct(
        public string $service,
        public string $type,
        public ?string $message = null,
        public ?string $model = null,
        public ?int $httpStatus = null,
        public ?string $endpoint = null,
        public ?string $subject = null,
    ) {}
}
