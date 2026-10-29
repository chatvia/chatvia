<?php

use Database\Factories\AgentFileFactory;
use Domain\Agent\Models\Agent;
use Domain\Knowledge\Ingestion\Actions\Sources\IngestMediaAction;
use Domain\Knowledge\Ingestion\Contracts\TextSplitter;
use Domain\Knowledge\Ingestion\Enums\ContentKind;
use Domain\Knowledge\Ingestion\Jobs\Media\MediaChunkIngestionJob;
use Illuminate\Bus\PendingBatch;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\Support\Knowledge\Ingestion\FakeTextSplitter;

/**
 * IngestMediaAction turns an audio/video transcript into a batch of chunk
 * jobs. A retry chunks the stored transcript, never the audio file, and the
 * batch id must land in `internal_chunking_batch_id`, the column
 * CancelFileIngestionAction reads, or deleting a file mid-ingestion can't
 * stop it. A blank transcript fails instead of chunking nothing.
 */
beforeEach(function (): void {
    Storage::fake();
    Bus::fake();
});

it('chunks the stored transcript on a retry and stores the batch id where cancel() looks for it', function (): void {
    $agent = Agent::factory()->create();
    $sourceFile = AgentFileFactory::new()->create([
        'status' => 'uploaded',
        'mime_type' => 'audio/mpeg',
        'filepath' => 'users/x/agents/y/files/audio.mp3',
        'parser_filepath' => 'users/x/agents/y/audio/transcript.txt',
    ]);
    $agent->files()->attach($sourceFile->id);
    Storage::put($sourceFile->filepath, 'ID3-binary-audio-bytes');
    Storage::put($sourceFile->parser_filepath, 'Hello from the stored transcript.');

    app(IngestMediaAction::class)->execute($sourceFile, $agent, 'queue-job-uuid');

    /** @var FakeTextSplitter $splitter */
    $splitter = app(TextSplitter::class);
    $sourceFile->refresh();

    expect($splitter->kinds)->toBe([ContentKind::Transcript])
        ->and(Bus::findBatch((string) $sourceFile->internal_chunking_batch_id)?->name)->toBe("Ingestion {$sourceFile->getMorphClass()} ID: {$sourceFile->id}")
        ->and($sourceFile->internal_ingestion_job_id)->toBe('queue-job-uuid');

    Bus::assertBatched(function (PendingBatch $batch): bool {
        $job = $batch->jobs->first();

        return $job instanceof MediaChunkIngestionJob
            && Str::contains($job->group->pages[0]->content, 'stored transcript')
            && ! Str::contains($job->group->pages[0]->content, 'ID3');
    });
});

it('fails on a blank transcript instead of chunking nothing', function (): void {
    $agent = Agent::factory()->create();
    $sourceFile = AgentFileFactory::new()->create([
        'workspace_id' => $agent->workspace_id,
        'status' => 'uploaded',
        'mime_type' => 'audio/mpeg',
        'parser_filepath' => 'users/x/agents/y/audio/transcript.txt',
    ]);
    Storage::put($sourceFile->parser_filepath, "  \n\t ");

    expect(fn () => app(IngestMediaAction::class)->execute($sourceFile, $agent))
        ->toThrow(RuntimeException::class, 'The transcript is empty: there is nothing to chunk.');

    Bus::assertNothingBatched();
});
