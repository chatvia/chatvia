<?php

use Database\Factories\AgentFileFactory;
use Domain\Agent\Models\Agent;
use Domain\Knowledge\Ingestion\Actions\Sources\IngestMediaAction;
use Domain\Knowledge\Ingestion\Contracts\TextSplitter;
use Domain\Knowledge\Ingestion\Enums\ContentKind;
use Domain\Knowledge\Ingestion\Jobs\Media\MediaChunkIngestionJob;
use Domain\Knowledge\Ingestion\Services\Media\AudioCompressor;
use Domain\Knowledge\Source\Enums\SourceStatus;
use Domain\Knowledge\Source\Services\KnowledgeStoragePath;
use Domain\Llm\Contracts\Transcriber;
use Illuminate\Bus\PendingBatch;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\Support\Knowledge\Ingestion\FakeTextSplitter;
use Tests\Support\Llm\FakeTranscriber;

/**
 * IngestMediaAction turns an audio/video transcript into a batch of chunk
 * jobs. The batch id must land in `internal_chunking_batch_id`, the column
 * CancelFileIngestionAction reads, or deleting a file mid-ingestion can't stop it.
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

    expect(Bus::findBatch((string) $sourceFile->internal_chunking_batch_id)?->name)->toBe("Ingestion {$sourceFile->getMorphClass()} ID: {$sourceFile->id}")
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

    /** @var FakeTextSplitter $splitter */
    $splitter = app(TextSplitter::class);

    expect($splitter->kinds)->toBe([ContentKind::Transcript]);

    Bus::assertBatched(function (PendingBatch $batch): bool {
        $job = $batch->jobs->first();

        return $job instanceof MediaChunkIngestionJob
            && Str::contains($job->group->pages[0]->content, 'stored transcript')
            && ! Str::contains($job->group->pages[0]->content, 'ID3');
    });
});

it('stores a new transcript in the source folder', function (): void {
    Storage::fake();
    Bus::fake();

    $agent = Agent::factory()->create();
    $sourceFile = AgentFileFactory::new()->create([
        'workspace_id' => $agent->workspace_id,
        'status' => 'uploaded',
        'mime_type' => 'audio/mpeg',
    ]);
    $agent->files()->attach($sourceFile->id);
    Storage::put($sourceFile->filepath, 'ID3-binary-audio-bytes');

    $this->mock(AudioCompressor::class)->shouldNotReceive('process');
    app()->instance(Transcriber::class, new FakeTranscriber('Hello from a new transcript.', name: 'fake-whisper'));

    app(IngestMediaAction::class)->execute($sourceFile, $agent);

    $fresh = $sourceFile->fresh();

    expect($fresh->parser_filepath)->toStartWith(KnowledgeStoragePath::forSource($sourceFile).'/')
        ->and(Storage::get($fresh->parser_filepath))->toBe('Hello from a new transcript.');
});

it('fails on a blank transcript instead of chunking nothing', function (): void {
    Storage::fake();
    Bus::fake();

    $agent = Agent::factory()->create();
    $sourceFile = AgentFileFactory::new()->create([
        'workspace_id' => $agent->workspace_id,
        'status' => 'uploaded',
        'mime_type' => 'audio/mpeg',
        'parser_filepath' => 'users/x/agents/y/audio/transcript.txt',
    ]);
    Storage::put($sourceFile->parser_filepath, "  \n\t ");

    expect(function () use ($sourceFile, $agent): void {
        app(IngestMediaAction::class)->execute($sourceFile, $agent);
    })->toThrow(RuntimeException::class, 'The transcript is empty: there is nothing to chunk.');

    Bus::assertNothingBatched();
});

it('leaves a file that is already ingested alone', function (): void {
    Storage::fake();
    Bus::fake();

    $agent = Agent::factory()->create();
    $sourceFile = AgentFileFactory::new()->create([
        'workspace_id' => $agent->workspace_id,
        'status' => 'ingested',
        'mime_type' => 'audio/mpeg',
    ]);
    $this->mock(Transcriber::class)->shouldNotReceive('transcribe', 'name');

    app(IngestMediaAction::class)->execute($sourceFile, $agent, 'queue-job-uuid');

    $fresh = $sourceFile->fresh();

    expect($fresh->status)->toBe(SourceStatus::Completed)
        ->and($fresh->internal_ingestion_job_id)->toBeNull()
        ->and($fresh->parser_filepath)->toBeNull();

    Bus::assertNothingBatched();
});
