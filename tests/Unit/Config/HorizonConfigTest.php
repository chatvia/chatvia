<?php

use App\Identity\Workspace\Jobs\DeleteWorkspaceJob;
use App\Identity\Workspace\Jobs\PrepareDataExportJob;
use Domain\Agent\Jobs\PurgeAgentJob;
use Domain\Channel\Instagram\Jobs\HandleInstagramMessageJob;
use Domain\Channel\Messenger\Jobs\HandleMessengerMessageJob;
use Domain\Channel\Slack\Jobs\HandleSlackEventJob;
use Domain\Channel\Teams\Jobs\HandleTeamsActivityJob;
use Domain\Channel\Telegram\Jobs\HandleTelegramUpdateJob;
use Domain\Channel\Whatsapp\Jobs\HandleWhatsappMessageJob;
use Domain\Conversation\Jobs\JoinVoiceCallJob;
use Domain\Knowledge\Ingestion\Jobs\Document\DocumentChunkIngestionJob;
use Domain\Knowledge\Ingestion\Jobs\Document\DocumentIngestionJob;
use Domain\Knowledge\Ingestion\Jobs\Media\MediaChunkIngestionJob;
use Domain\Knowledge\Ingestion\Jobs\Media\MediaIngestionJob;
use Domain\Knowledge\Ingestion\Jobs\SummaryIngestionJob;
use Domain\Knowledge\Ingestion\Jobs\Text\TextChunkIngestionJob;
use Domain\Knowledge\Ingestion\Jobs\TextFile\TextFileIngestionJob;
use Domain\Knowledge\Ingestion\Jobs\Website\WebsiteIngestionJob;
use Domain\Knowledge\Ingestion\Jobs\Website\WebsiteUrlTrainJob;

/**
 * Horizon's supervisors must cover every queue the app uses, and a queue
 * connection's retry_after must outlast every job it carries: otherwise a
 * job that is still running is handed to a second worker. A self-hosted
 * server must also not start more workers than it has memory for.
 */

/** @return array<string, array<string, mixed>> */
function horizonSupervisors(): array
{
    return config('horizon.defaults');
}

/** The supervisor that works on a queue. */
function horizonSupervisorFor(string $queue): ?string
{
    foreach (horizonSupervisors() as $name => $supervisor) {
        if (in_array($queue, (array) $supervisor['queue'], true)) {
            return $name;
        }
    }

    return null;
}

it('works on every queue the app uses, each in one supervisor', function (): void {
    $used = [
        'messages', 'default', config('chatvia.analytics.queue'), 'exports',
        'ingestion.chunks', 'ingestion.summary', 'ingestion.text', 'ingestion.pdf',
        'ingestion.word', 'ingestion.website', 'ingestion.audio', 'ingestion.video', 'voice',
    ];
    $configured = collect(horizonSupervisors())->flatMap(fn (array $supervisor): array => (array) $supervisor['queue']);

    expect($configured->duplicates()->all())->toBe([])
        ->and(array_values(array_filter($used, fn (string $queue): bool => horizonSupervisorFor($queue) === null)))->toBe([]);
});

it('gives every supervisor a connection whose retry_after outlasts its timeout', function (): void {
    foreach (horizonSupervisors() as $name => $supervisor) {
        $retryAfter = config("queue.connections.{$supervisor['connection']}.retry_after");

        expect($retryAfter)->toBeGreaterThan($supervisor['timeout'], "{$name}: retry_after {$retryAfter} <= timeout {$supervisor['timeout']}");
    }
});

it('pops each long job through a connection whose retry_after outlasts the job', function (string $job, string $queue): void {
    $timeout = jobTimeout($job);
    $supervisor = horizonSupervisorFor($queue);
    $retryAfter = config('queue.connections.'.horizonSupervisors()[$supervisor]['connection'].'.retry_after');

    // A job with a fixed queue is listed under it; one that picks its queue
    // per file (pdf or word, audio or video) has none on the class.
    expect(jobQueue($job))->toBeIn([$queue, null])
        ->and($timeout)->toBeInt()
        ->and($retryAfter)->toBeGreaterThan($timeout);
})->with([
    [HandleSlackEventJob::class, 'messages'],
    [HandleTeamsActivityJob::class, 'messages'],
    [HandleTelegramUpdateJob::class, 'messages'],
    [HandleWhatsappMessageJob::class, 'messages'],
    [HandleMessengerMessageJob::class, 'messages'],
    [HandleInstagramMessageJob::class, 'messages'],
    [MediaIngestionJob::class, 'ingestion.audio'],
    [MediaIngestionJob::class, 'ingestion.video'],
    [DocumentIngestionJob::class, 'ingestion.pdf'],
    [DocumentIngestionJob::class, 'ingestion.word'],
    [DocumentChunkIngestionJob::class, 'ingestion.chunks'],
    [MediaChunkIngestionJob::class, 'ingestion.chunks'],
    [TextChunkIngestionJob::class, 'ingestion.chunks'],
    [TextFileIngestionJob::class, 'ingestion.text'],
    [SummaryIngestionJob::class, 'ingestion.summary'],
    [WebsiteIngestionJob::class, 'ingestion.website'],
    [WebsiteUrlTrainJob::class, 'ingestion.website'],
    [PrepareDataExportJob::class, 'exports'],
    [DeleteWorkspaceJob::class, 'exports'],
    [PurgeAgentJob::class, 'exports'],
    [JoinVoiceCallJob::class, 'voice'],
]);

it('runs the export and clean-up jobs on the exports queue for up to 30 minutes', function (string $job): void {
    expect(jobQueue($job))->toBe('exports')
        ->and(jobTimeout($job))->toBe(1800);
})->with([PrepareDataExportJob::class, DeleteWorkspaceJob::class, PurgeAgentJob::class]);

it('runs the same supervisors in every environment', function (): void {
    expect(config('horizon.environments'))->toBe(['*' => []]);
});

it('fits a small server', function (): void {
    $most = collect(horizonSupervisors())->sum('maxProcesses');
    $idle = collect(horizonSupervisors())->sum(fn (array $supervisor): int => $supervisor['balance'] === 'auto'
        ? count((array) $supervisor['queue']) * $supervisor['minProcesses']
        : $supervisor['minProcesses']);

    expect($most)->toBeLessThanOrEqual(16)
        ->and($idle)->toBeLessThanOrEqual(6);
});
