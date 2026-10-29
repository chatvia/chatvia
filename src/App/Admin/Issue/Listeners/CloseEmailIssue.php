<?php

declare(strict_types=1);

namespace App\Admin\Issue\Listeners;

use App\Admin\Issue\Services\IssueReporter;
use Support\Radar\ServiceKey;

/**
 * An email went out, so email works again: an open "Emails cannot be
 * sent" issue closes. Laravel's own event is the only sign of it.
 */
final readonly class CloseEmailIssue
{
    public function __construct(private IssueReporter $reporter) {}

    public function handle(): void
    {
        $this->reporter->recovered(ServiceKey::MAIL);
    }
}
