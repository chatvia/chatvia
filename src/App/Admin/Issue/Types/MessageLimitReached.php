<?php

declare(strict_types=1);

namespace App\Admin\Issue\Types;

use App\Admin\Issue\Data\IssueSubject;
use Support\Radar\IssueTypeKey;

/**
 * A workspace used up the monthly message limit an admin set, so its
 * agents stop answering. One issue per workspace; it closes when the limit
 * is raised or the month restarts.
 */
final class MessageLimitReached extends WorkspaceLimitType
{
    public function key(): string
    {
        return IssueTypeKey::MESSAGE_LIMIT_REACHED;
    }

    public function label(): string
    {
        return 'Message limit reached';
    }

    public function title(IssueSubject $subject): string
    {
        return "{$this->subjectLabel($subject->subject ?? '')} reached its monthly message limit";
    }

    public function advice(IssueSubject $subject): string
    {
        return "Its agents stop answering until the month restarts. If it needs more, raise the message limit on the workspace's page.";
    }
}
