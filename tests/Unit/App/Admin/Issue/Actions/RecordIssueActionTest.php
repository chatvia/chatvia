<?php

use App\Admin\Issue\Actions\RecordIssueAction;
use App\Admin\Issue\Data\IssueActorData;
use App\Admin\Issue\Data\ReportedIssueData;
use App\Admin\Issue\Enums\IssueActorKind;
use App\Admin\Issue\Enums\IssueOriginKind;
use App\Admin\Issue\Enums\IssueStatus;
use App\Admin\Issue\Models\Issue;
use App\Admin\Issue\Models\IssueOccurrence;
use App\Admin\Issue\Services\IssueReporter;
use Domain\Conversation\Jobs\GenerateMediaJob;
use Illuminate\Support\Facades\Cache;
use Support\Radar\IssueRaised;
use Support\Radar\IssueTypeKey;
use Support\Radar\ServiceKey;

/**
 * One issue per service and kind (and subject, for a per-subject kind), one
 * occurrence per time it happened. A resolved issue opens again when it
 * comes back; an ignored one stays ignored. A burst is counted without rows.
 * The service is marked so its next success closes the issue.
 */
function riaReported(string $type = IssueTypeKey::KEY_REFUSED, ?string $message = 'Invalid API key', ?string $subject = null, string $service = 'fal'): ReportedIssueData
{
    return new ReportedIssueData(
        issue: new IssueRaised($service, $type, $message, httpStatus: 403, endpoint: 'queue.fal.run/fal-ai/wan', subject: $subject),
        originKind: IssueOriginKind::Job,
        originName: GenerateMediaJob::class,
        actor: new IssueActorData(IssueActorKind::System),
        workspaceId: null,
        agentId: null,
        occurredAt: now()->toImmutable(),
    );
}

it('groups the same issue and marks the service as having one open', function (): void {
    $record = app(RecordIssueAction::class);
    $record->execute(riaReported(message: 'Invalid API key'));
    $issue = $record->execute(riaReported(message: 'Key was deleted'));

    expect(Issue::query()->count())->toBe(1)
        ->and($issue->occurrences_count)->toBe(2)
        ->and($issue->message)->toBe('Key was deleted')
        ->and($issue->title())->toBe('fal.ai refused the API key')
        ->and($issue->occurrences()->count())->toBe(2)
        ->and(Cache::has(IssueReporter::openKey('fal')))->toBeTrue();
});

it('keeps each subject of a per-subject kind apart, and ignores the subject of another kind', function (): void {
    $record = app(RecordIssueAction::class);
    $record->execute(riaReported(IssueTypeKey::JOB_FAILED, subject: 'App\\Jobs\\A', service: ServiceKey::QUEUE));
    $record->execute(riaReported(IssueTypeKey::JOB_FAILED, subject: 'App\\Jobs\\B', service: ServiceKey::QUEUE));
    $record->execute(riaReported(subject: 'ignored'));

    expect(Issue::query()->count())->toBe(3)
        ->and(Issue::query()->where('kind', IssueTypeKey::KEY_REFUSED)->value('subject'))->toBeNull();
});

it('opens a resolved issue again, but leaves an ignored one ignored', function (): void {
    $record = app(RecordIssueAction::class);
    $resolved = $record->execute(riaReported(IssueTypeKey::KEY_REFUSED));
    $ignored = $record->execute(riaReported(IssueTypeKey::OUT_OF_CREDIT));
    $resolved->resolve();
    $ignored->ignore();

    expect($record->execute(riaReported(IssueTypeKey::KEY_REFUSED))->status)->toBe(IssueStatus::Open)
        ->and($record->execute(riaReported(IssueTypeKey::OUT_OF_CREDIT))->status)->toBe(IssueStatus::Ignored)
        ->and($resolved->fresh()?->resolution)->toBeNull();
});

it('only counts a burst of one issue past the minute limit', function (): void {
    $issue = app(RecordIssueAction::class)->execute(riaReported());
    IssueOccurrence::factory()->count(RecordIssueAction::MAX_OCCURRENCES_PER_MINUTE - 1)->create(['issue_id' => $issue->id]);

    $issue = app(RecordIssueAction::class)->execute(riaReported());

    expect($issue->occurrences_count)->toBe(2)
        ->and($issue->occurrences()->count())->toBe(RecordIssueAction::MAX_OCCURRENCES_PER_MINUTE);
});
