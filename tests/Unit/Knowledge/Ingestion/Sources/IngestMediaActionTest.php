<?php

use Database\Factories\AgentFileFactory;
use Domain\Agent\Models\Agent;
use Domain\Knowledge\Ingestion\Actions\Sources\IngestMediaAction;
use Domain\Knowledge\Ingestion\Jobs\Media\MediaChunkIngestionJob;
use Illuminate\Bus\PendingBatch;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;

/**
 * IngestMediaAction turns an audio/video transcript into a batch of chunk
 * jobs. The batch id must land in `internal_chunking_batch_id`, the column
 * Ingestion::cancel() reads, or deleting a file mid-ingestion can't stop it.
 */
it('stores the chunking batch id where cancel() looks for it', function (): void {
    Storage::fake();
    Bus::fake();

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

    $sourceFile->refresh();

    expect($sourceFile->internal_chunking_batch_id)->not->toBeNull()
        ->and($sourceFile->internal_ingestion_job_id)->toBe('queue-job-uuid');
});

it('chunks the stored transcript, not the audio file, on a retry', function (): void {
    Storage::fake();
    Bus::fake();

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

    app(IngestMediaAction::class)->execute($sourceFile, $agent);

    Bus::assertBatched(function (PendingBatch $batch): bool {
        $job = $batch->jobs->first();

        return $job instanceof MediaChunkIngestionJob
            && str_contains($job->page->content, 'stored transcript')
            && ! str_contains($job->page->content, 'ID3');
    });
});
