<?php

declare(strict_types=1);

namespace App\Admin\Issue\Types;

use App\Admin\Clusters\Settings\Pages\ManagePlatformSettings;
use App\Admin\Issue\Data\IssueActionData;
use App\Admin\Issue\Data\IssueSubject;
use Illuminate\Support\Str;
use Support\Radar\IssueTypeKey;

/**
 * Jobs wait on a queue longer than Horizon allows: the workers cannot keep
 * up. One issue per queue; it closes once the wait is short again.
 */
final class QueueBacklog extends IssueType
{
    public function key(): string
    {
        return IssueTypeKey::QUEUE_BACKLOG;
    }

    public function label(): string
    {
        return 'Queue falling behind';
    }

    public function title(IssueSubject $subject): string
    {
        return "The {$this->subjectLabel($subject->subject ?? '')} queue is falling behind";
    }

    public function advice(IssueSubject $subject): string
    {
        return 'Jobs on this queue wait longer than they should, so replies, training or exports come late. Give it more workers in Settings → Platform → Queue workers, or check in Horizon what is slowing it down.';
    }

    public function actions(IssueSubject $subject): array
    {
        return [new IssueActionData('Open queue workers settings', ManagePlatformSettings::getUrl()), ...(new JobFailed)->actions($subject)];
    }

    /** The queue's name without its connection: "records". */
    public function subjectLabel(string $subject): string
    {
        return Str::afterLast($subject, ':');
    }

    public function perSubject(): bool
    {
        return true;
    }
}
