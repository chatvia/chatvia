<?php

declare(strict_types=1);

namespace App\Admin\Issue\Listeners;

use App\Admin\Issue\Services\IssueReporter;
use Support\Radar\ServiceRecovered;

/** A service works again: Radar closes its open issues that close by themselves. */
final readonly class CloseRecoveredService
{
    public function __construct(private IssueReporter $reporter) {}

    public function handle(ServiceRecovered $event): void
    {
        $this->reporter->recovered($event->service, $event->subject, $event->type);
    }
}
