<?php

declare(strict_types=1);

namespace App\Admin\Issue\Types;

use App\Admin\Issue\Data\IssueActionData;
use App\Admin\Issue\Data\IssueSubject;
use App\Admin\StaffTool;
use Illuminate\Support\Str;
use Support\Radar\IssueTypeKey;

/**
 * A background job failed after all its tries. One issue per job class;
 * it closes by itself once that job runs fine again.
 */
final class JobFailed extends IssueType
{
    public function key(): string
    {
        return IssueTypeKey::JOB_FAILED;
    }

    public function label(): string
    {
        return 'Job failed';
    }

    public function title(IssueSubject $subject): string
    {
        return "The background job {$this->subjectLabel($subject->subject ?? '')} failed";
    }

    public function advice(IssueSubject $subject): string
    {
        return 'A background job failed after all its tries, so the work it does did not happen. Open the failed jobs in Horizon to see the error and retry it. The issue closes by itself once the job runs fine again.';
    }

    public function actions(IssueSubject $subject): array
    {
        return StaffTool::Horizon->unavailableReason() === null
            ? [new IssueActionData('Open failed jobs in Horizon', Str::of(StaffTool::Horizon->url())->finish('/')->append('failed')->toString(), external: true)]
            : [];
    }

    /** The job's class name without its namespace: "GenerateMediaJob". */
    public function subjectLabel(string $subject): string
    {
        return class_basename($subject);
    }

    public function perSubject(): bool
    {
        return true;
    }
}
