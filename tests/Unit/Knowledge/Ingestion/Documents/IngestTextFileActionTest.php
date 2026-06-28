<?php

use Database\Factories\AgentFileFactory;
use Domain\Agent\Models\Agent;
use Domain\Knowledge\Document\Pandoc;
use Domain\Knowledge\Ingestion\Actions\Documents\IngestTextFileAction;
use Domain\Knowledge\Ingestion\Services\Chunking\ChunkFit\ChunkFitClient;
use Domain\Knowledge\Source\Enums\SourceStatus;
use Domain\Knowledge\Source\File\Events\FileIngestionProgressed;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;

/**
 * IngestTextFileAction chunks a text-based SourceFile directly via chunk.fit
 * (no LibreOffice/PDF/OCR) and rides the shared DocumentChunkIngestionJob
 * batch. Raw text (.txt/.md) is read as-is; Pandoc-extractable formats are
 * converted to plain text first. Both collaborators are faked so the test
 * never hits chunk.fit's HTTP surface or the pandoc binary — we assert the
 * orchestration: idempotency, the empty-input terminal broadcast, real-chunk
 * fan-out, and that Pandoc-extractable formats are routed through Pandoc.
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

function itfFakePandoc(string $extracted = ''): Pandoc
{
    return new class($extracted) extends Pandoc
    {
        public function __construct(private string $extracted) {}

        public function toText(string $path, string $extension): string
        {
            $GLOBALS['itf_pandoc_called'] = true;

            return $this->extracted;
        }
    };
}

function itfAction(ChunkFitClient $chunker, ?Pandoc $pandoc = null): IngestTextFileAction
{
    return new IngestTextFileAction($chunker, $pandoc ?? itfFakePandoc());
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
        'filename' => 'guide.txt',
    ]);
    Storage::put($sourceFile->filepath, "# Title\n\nBody text.");
    $agent->files()->attach($sourceFile->id);

    itfAction(itfFakeChunkFit(['# Title', 'Body text.']))->execute($sourceFile, $agent);

    Bus::assertBatched(fn ($batch): bool => str_contains((string) $batch->name, (string) $sourceFile->id));
    expect($sourceFile->fresh()->status)->toBe(SourceStatus::Processing->value)
        ->and((int) $sourceFile->fresh()->expected_chunks)->toBe(2)
        ->and($sourceFile->fresh()->parser)->toBe('chunkfit');
});

it('extracts text via Pandoc for a Pandoc-supported format before chunking', function (): void {
    Bus::fake();
    Storage::fake();
    $GLOBALS['itf_pandoc_called'] = false;

    $agent = Agent::factory()->create();
    $sourceFile = AgentFileFactory::new()->create([
        'status' => 'uploaded',
        'filename' => 'book.epub',
    ]);
    Storage::put($sourceFile->filepath, 'binary-epub-bytes');
    $agent->files()->attach($sourceFile->id);

    itfAction(itfFakeChunkFit(['Chapter one text']), itfFakePandoc('Chapter one text'))
        ->execute($sourceFile, $agent);

    expect($GLOBALS['itf_pandoc_called'])->toBeTrue();
    Bus::assertBatched(fn ($batch): bool => str_contains((string) $batch->name, (string) $sourceFile->id));
});
