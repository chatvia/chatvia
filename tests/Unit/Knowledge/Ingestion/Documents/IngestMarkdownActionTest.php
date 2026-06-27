<?php

use Database\Factories\AgentFileFactory;
use Domain\Agent\Models\Agent;
use Domain\Knowledge\Ingestion\Actions\Documents\IngestMarkdownAction;
use Domain\Knowledge\Ingestion\Services\Chunking\ChunkFit\ChunkFitClient;
use Domain\Knowledge\Source\Enums\SourceStatus;
use Domain\Knowledge\Source\File\Events\FileIngestionProgressed;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;

/**
 * IngestMarkdownAction chunks a Markdown SourceFile directly via chunk.fit
 * (no LibreOffice/PDF/OCR) and rides the shared DocumentChunkIngestionJob
 * batch. The chunk.fit collaborator is faked so the test never hits its HTTP
 * surface — we assert the orchestration: idempotency, the empty-input terminal
 * broadcast, and that real chunks fan out through a Bus batch.
 */
function imaFakeChunkFit(array $chunks): ChunkFitClient
{
    return new class($chunks) extends ChunkFitClient
    {
        public function __construct(private array $chunks) {}

        public function splitByTitle(string $text): array
        {
            return $this->chunks;
        }
    };
}

it('is idempotent: an already-ingested SourceFile returns immediately', function (): void {
    Bus::fake();
    Event::fake([FileIngestionProgressed::class]);

    $agent = Agent::factory()->create();
    $sourceFile = AgentFileFactory::new()->create([
        'status' => SourceStatus::Completed->value,
        'filetype' => 'markdown',
    ]);
    $agent->files()->attach($sourceFile->id);

    (new IngestMarkdownAction(imaFakeChunkFit(['# A'])))->execute($sourceFile, $agent);

    Bus::assertNothingBatched();
    Event::assertNotDispatched(FileIngestionProgressed::class);
});

it('finishes cleanly with a terminal broadcast when Markdown yields no chunks', function (): void {
    Bus::fake();
    Event::fake([FileIngestionProgressed::class]);
    Storage::fake();

    $agent = Agent::factory()->create();
    $sourceFile = AgentFileFactory::new()->create([
        'status' => 'uploaded',
        'filetype' => 'markdown',
    ]);
    Storage::put($sourceFile->filepath, '   ');
    $agent->files()->attach($sourceFile->id);

    (new IngestMarkdownAction(imaFakeChunkFit([])))->execute($sourceFile, $agent);

    Bus::assertNothingBatched();
    Event::assertDispatched(FileIngestionProgressed::class);
    expect($sourceFile->fresh()->status)->toBe(SourceStatus::Completed->value)
        ->and((int) $sourceFile->fresh()->expected_chunks)->toBe(0)
        ->and($sourceFile->fresh()->parser)->toBe('chunkfit');
});

it('fans real chunks out through a Bus batch named with the SourceFile id', function (): void {
    Bus::fake();
    Storage::fake();

    $agent = Agent::factory()->create();
    $sourceFile = AgentFileFactory::new()->create([
        'status' => 'uploaded',
        'filetype' => 'markdown',
    ]);
    Storage::put($sourceFile->filepath, "# Title\n\nBody text.");
    $agent->files()->attach($sourceFile->id);

    (new IngestMarkdownAction(imaFakeChunkFit(['# Title', 'Body text.'])))->execute($sourceFile, $agent);

    Bus::assertBatched(fn ($batch): bool => str_contains((string) $batch->name, (string) $sourceFile->id));
    expect($sourceFile->fresh()->status)->toBe(SourceStatus::Processing->value)
        ->and((int) $sourceFile->fresh()->expected_chunks)->toBe(2)
        ->and($sourceFile->fresh()->parser)->toBe('chunkfit');
});
