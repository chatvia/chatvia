<?php

declare(strict_types=1);

namespace App\Admin\Issue\Data;

use Spatie\LaravelData\Data;

/** What an issue's title, advice and buttons are about: the service and, for a per-subject kind, the subject (a model, a job class, a workspace id…). */
class IssueSubject extends Data
{
    public function __construct(
        public IssueServiceData $service,
        public ?string $subject = null,
    ) {}
}
