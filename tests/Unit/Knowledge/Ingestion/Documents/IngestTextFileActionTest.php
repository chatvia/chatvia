<?php

use Database\Factories\AgentFileFactory;
use Domain\Agent\Models\Agent;
use Domain\Knowledge\Document\Conversion\Pandoc;
use Domain\Knowledge\Ingestion\Actions\Documents\IngestTextFileAction;
use Domain\Knowledge\Ingestion\Contracts\TextSplitter;
use Domain\Knowledge\Ingestion\Enums\ContentKind;
use Domain\Knowledge\Ingestion\Services\Chunking\BuiltinTextSplitter;
use Domain\Knowledge\Source\Enums\SourceStatus;
use Domain\Knowledge\Source\File\Events\FileIngestionProgressed;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;

/**
 * IngestTextFileAction chunks a raw-text SourceFile (.txt/.md) directly with
 * the text splitter (no OCR), as Markdown or plain text, and rides the shared
 * DocumentChunkIngestionJob batch. The splitter is faked to return fixed
 * chunks, so we assert the orchestration: idempotency, the empty-input
 * terminal broadcast, real-chunk fan-out and the kind of content.
 */
function itfFakeSplitter(array $chunks): TextSplitter
{
    return new class($chunks) implements TextSplitter
    {
        /** @var list<ContentKind> */
        public array $kinds = [];

        public function __construct(private readonly array $chunks) {}

        public function split(string $text, ContentKind $kind): array
        {
            $this->kinds[] = $kind;

            return $this->chunks;
        }

        public function name(): string
        {
            return 'fixed';
        }
    };
}

function itfFakePandoc(string $markdown): Pandoc
{
    return new class($markdown) extends Pandoc
    {
        public function __construct(private readonly string $markdown) {}

        public function toMarkdown(string $path, string $extension): string
        {
            return $this->markdown;
        }
    };
}

function itfAction(TextSplitter $splitter, ?Pandoc $pandoc = null): IngestTextFileAction
{
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

    itfAction(itfFakeSplitter(['# A']))->execute($sourceFile, $agent);

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

    itfAction(itfFakeSplitter([]))->execute($sourceFile, $agent);

    Bus::assertNothingBatched();
    Event::assertDispatched(FileIngestionProgressed::class);
    expect($sourceFile->fresh()->status)->toBe(SourceStatus::Completed)
        ->and((int) $sourceFile->fresh()->expected_chunks)->toBe(0)
        ->and($sourceFile->fresh()->parser)->toBe('fixed');
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

    itfAction(itfFakeSplitter(['# Title', 'Body text.']))->execute($sourceFile, $agent);

    Bus::assertBatched(fn ($batch): bool => str_contains((string) $batch->name, (string) $sourceFile->id));
    expect($sourceFile->fresh()->status)->toBe(SourceStatus::Processing)
        ->and((int) $sourceFile->fresh()->expected_chunks)->toBe(2)
        ->and($sourceFile->fresh()->parser)->toBe('fixed');
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
    itfAction(itfFakeSplitter(['# Chapter 1', 'The book body.']), $pandoc)
        ->execute($sourceFile, $agent);

    Bus::assertBatched(fn ($batch): bool => str_contains((string) $batch->name, (string) $sourceFile->id));
    expect($sourceFile->fresh()->status)->toBe(SourceStatus::Processing)
        ->and((int) $sourceFile->fresh()->expected_chunks)->toBe(2)
        ->and($sourceFile->fresh()->parser)->toBe('fixed');
});

it('splits Markdown files and ebooks as Markdown, and .txt files as plain text', function (string $filename, ContentKind $kind): void {
    Bus::fake();
    Storage::fake();

    $agent = Agent::factory()->create();
    $sourceFile = AgentFileFactory::new()->create(['status' => 'uploaded', 'filename' => $filename]);
    Storage::put($sourceFile->filepath, 'Some text.');
    $agent->files()->attach($sourceFile->id);

    $splitter = itfFakeSplitter(['Some text.']);
    itfAction($splitter, itfFakePandoc('# Some text.'))->execute($sourceFile, $agent);

    expect($splitter->kinds)->toBe([$kind]);
})->with([
    'txt' => ['guide.txt', ContentKind::Text],
    'md' => ['notes.md', ContentKind::Markdown],
    'markdown' => ['notes.markdown', ContentKind::Markdown],
    'epub' => ['book.epub', ContentKind::Markdown],
]);

it('splits with the built-in splitter and records its name', function (): void {
    Bus::fake();
    Storage::fake();
    config(['chatvia.ai.chunking' => ['unit' => 'characters', 'size' => 200, 'overlap' => 0, 'strategies' => ['markdown' => 'markdown']]]);

    $agent = Agent::factory()->create();
    $sourceFile = AgentFileFactory::new()->create(['status' => 'uploaded', 'filename' => 'notes.md']);
    Storage::put($sourceFile->filepath, "# Returns\n\n".str_repeat('You can return any item within 30 days. ', 6)."\n\n# Shipping\n\nWe ship in two days.");
    $agent->files()->attach($sourceFile->id);

    itfAction(new BuiltinTextSplitter)->execute($sourceFile, $agent);

    Bus::assertBatched(fn ($batch): bool => str_contains((string) $batch->name, (string) $sourceFile->id));
    expect($sourceFile->fresh()->parser)->toBe('builtin')
        ->and((int) $sourceFile->fresh()->expected_chunks)->toBe(3);
});
