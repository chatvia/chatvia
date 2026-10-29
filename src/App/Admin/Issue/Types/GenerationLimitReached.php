<?php

declare(strict_types=1);

namespace App\Admin\Issue\Types;

use App\Admin\Issue\Data\IssueSubject;
use Support\Radar\IssueTypeKey;

/**
 * A workspace used up a monthly generation limit an admin set (pictures and
 * audio, or videos), so its agents make no more this month. One issue per
 * workspace; it closes when the limit is raised or the month restarts.
 */
final class GenerationLimitReached extends WorkspaceLimitType
{
    public function key(): string
    {
        return IssueTypeKey::GENERATION_LIMIT_REACHED;
    }

    public function label(): string
    {
        return 'Generation limit reached';
    }

    public function title(IssueSubject $subject): string
    {
        return "{$this->subjectLabel($subject->subject ?? '')} reached its monthly limit on pictures, audio or videos";
    }

    public function advice(IssueSubject $subject): string
    {
        return "Its agents make no more files of that kind until the month restarts. If it needs more, raise the generation limit on the workspace's page.";
    }
}
