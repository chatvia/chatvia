<?php

use Database\Factories\AgentFileFactory;
use Domain\Agent\Models\Agent;
use Domain\Knowledge\Document\Conversion\Pandoc;
use Domain\Knowledge\Ingestion\Actions\Documents\IngestTextFileAction;
use Domain\Knowledge\Ingestion\Contracts\TextSplitter;
use Domain\Knowledge\Ingestion\Services\Chunking\ChunkFit\ChunkFitClient;
use Domain\Knowledge\Ingestion\Services\Chunking\ChunkFitTextSplitter;
use Domain\Knowledge\Ingestion\Services\Chunking\LocalTextSplitter;
use Domain\Knowledge\Source\Enums\SourceStatus;
use Domain\Knowledge\Source\File\Events\FileIngestionProgressed;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;

/**
 * IngestTextFileAction chunks a raw-text SourceFile (.txt/.md) directly with
 * the configured TextSplitter (no OCR) and rides the shared
 * DocumentChunkIngestionJob batch. The chunk.fit client is faked so the test
 * never hits its HTTP surface — we assert the orchestration: idempotency, the
 * empty-input terminal broadcast, and real-chunk fan-out.
 */
function itfFakeChunkFit(array $chunks): ChunkFitClient
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

function itfFakePandoc(string $markdown): Pandoc
{
    return new class($markdown) extends Pandoc
    {
        public function __construct(private string $markdown) {}

        public function toMarkdown(string $path, string $extension): string
        {
            return $this->markdown;
        }
    };
}

function itfAction(ChunkFitClient|TextSplitter $splitter, ?Pandoc $pandoc = null): IngestTextFileAction
{
    $splitter = $splitter instanceof ChunkFitClient ? new ChunkFitTextSplitter($splitter) : $splitter;

    return app()->make(IngestTextFileAction::class, ['splitter' => $splitter, 'pandoc' => $pandoc ?? itfFakePandoc('')]);
}

it('is idempotent: an already-ingested SourceFile returns immediately', function (): void {
    Bus::fake();
    Event::fake([FileIngestionProgressed::class]);

    $agent = Agent::factory()->create();
    $sourceFile = AgentFileFactory::new()->create([
        'status' => SourceStatus::Completed->value,
        'filename' => 'notes.md',
    ]);
    $agent->files()->attach($sourceFile->id);

    itfAction(itfFakeChunkFit(['# A']))->execute($sourceFile, $agent);

    Bus::assertNothingBatched();
    Event::assertNotDispatched(FileIngestionProgressed::class);
});

it('finishes cleanly with a terminal broadcast when the file yields no chunks', function (): void {
    Bus::fake();
    Event::fake([FileIngestionProgressed::class]);
    Storage::fake();

    $agent = Agent::factory()->create();
    $sourceFile = AgentFileFactory::new()->create([
        'status' => 'uploaded',
        'filename' => 'empty.txt',
    ]);
    Storage::put($sourceFile->filepath, '   ');
    $agent->files()->attach($sourceFile->id);

    itfAction(itfFakeChunkFit([]))->execute($sourceFile, $agent);

    Bus::assertNothingBatched();
    Event::assertDispatched(FileIngestionProgressed::class);
    expect($sourceFile->fresh()->status)->toBe(SourceStatus::Completed)
        ->and((int) $sourceFile->fresh()->expected_chunks)->toBe(0)
        ->and($sourceFile->fresh()->parser)->toBe('chunkfit');
});

it('fans real chunks out through a Bus batch named with the SourceFile id', function (): void {
    Bus::fake();
    Storage::fake();

    $agent = Agent::factory()->create();
    $sourceFile = AgentFileFactory::new()->create([
        'status' => 'uploaded',
        'filename' => 'guide.txt',
    ]);
    Storage::put($sourceFile->filepath, "# Title\n\nBody text.");
    $agent->files()->attach($sourceFile->id);

    itfAction(itfFakeChunkFit(['# Title', 'Body text.']))->execute($sourceFile, $agent);

    Bus::assertBatched(fn ($batch): bool => str_contains((string) $batch->name, (string) $sourceFile->id));
    expect($sourceFile->fresh()->status)->toBe(SourceStatus::Processing)
        ->and((int) $sourceFile->fresh()->expected_chunks)->toBe(2)
        ->and($sourceFile->fresh()->parser)->toBe('chunkfit');
});

it('extracts ebook (.epub/.fb2) text via Pandoc before chunking', function (): void {
    Bus::fake();
    Storage::fake();

    $agent = Agent::factory()->create();
    $sourceFile = AgentFileFactory::new()->create([
        'status' => 'uploaded',
        'filename' => 'book.epub',
    ]);
    // Raw ebook bytes — the Pandoc collaborator is faked, so the action never
    // reads them; it routes through toMarkdown() instead of getContent().
    Storage::put($sourceFile->filepath, 'epub-bytes');
    $agent->files()->attach($sourceFile->id);

    $pandoc = itfFakePandoc("# Chapter 1\n\nThe book body.");
    itfAction(itfFakeChunkFit(['# Chapter 1', 'The book body.']), $pandoc)
        ->execute($sourceFile, $agent);

    Bus::assertBatched(fn ($batch): bool => str_contains((string) $batch->name, (string) $sourceFile->id));
    expect($sourceFile->fresh()->status)->toBe(SourceStatus::Processing)
        ->and((int) $sourceFile->fresh()->expected_chunks)->toBe(2)
        ->and($sourceFile->fresh()->parser)->toBe('chunkfit');
});

it('splits with the local splitter when no splitting service is set up and records it', function (): void {
    Bus::fake();
    Storage::fake();

    $agent = Agent::factory()->create();
    $sourceFile = AgentFileFactory::new()->create([
        'status' => 'uploaded',
        'filename' => 'guide.txt',
    ]);
    Storage::put($sourceFile->filepath, "First paragraph about refunds.\n\nSecond paragraph about shipping.");
    $agent->files()->attach($sourceFile->id);

    itfAction(new LocalTextSplitter)->execute($sourceFile, $agent);

    Bus::assertBatched(fn ($batch): bool => str_contains((string) $batch->name, (string) $sourceFile->id));
    expect($sourceFile->fresh()->parser)->toBe('local')
        ->and((int) $sourceFile->fresh()->expected_chunks)->toBe(1);
});
