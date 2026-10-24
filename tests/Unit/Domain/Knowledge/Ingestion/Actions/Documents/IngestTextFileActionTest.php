<?php

use Database\Factories\AgentFileFactory;
use Domain\Agent\Models\Agent;
use Domain\Knowledge\Document\Conversion\EbookText;
use Domain\Knowledge\Ingestion\Actions\Documents\IngestTextFileAction;
use Domain\Knowledge\Ingestion\Actions\Pipeline\DispatchSourceIngestionBatchAction;
use Domain\Knowledge\Ingestion\Contracts\TextSplitter;
use Domain\Knowledge\Ingestion\Enums\ContentKind;
use Domain\Knowledge\Ingestion\Services\Chunking\BuiltinTextSplitter;
use Domain\Knowledge\Source\Enums\SourceStatus;
use Domain\Knowledge\Source\File\Events\FileIngestionProgressed;
use Domain\Knowledge\Source\File\Models\SourceFile;
use Illuminate\Bus\PendingBatch;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Tests\Support\Knowledge\Ingestion\FakeTextSplitter;

/**
 * IngestTextFileAction chunks a raw-text SourceFile (.txt/.md) directly with
 * the text splitter (no OCR), as Markdown or plain text, and rides the shared
 * DocumentChunkIngestionJob batch. The splitter is faked to return fixed
 * chunks (FakeTextSplitter::returning()), so these check the orchestration:
 * idempotency, handing an empty file to the shared batch step, real-chunk
 * fan-out and the kind of content.
 */
function itfFakeEbookText(string $markdown): EbookText
{
    return new class($markdown) extends EbookText
    {
        public function __construct(private readonly string $markdown) {}

        public function toMarkdown(string $path, string $extension): string
        {
            return $this->markdown;
        }
    };
}

function itfAction(TextSplitter $splitter, ?EbookText $ebookText = null): IngestTextFileAction
{
    return app()->make(IngestTextFileAction::class, ['splitter' => $splitter, 'ebookText' => $ebookText ?? itfFakeEbookText('')]);
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

    itfAction(FakeTextSplitter::returning('# A'))->execute($sourceFile, $agent);

    Bus::assertNothingBatched();
    Event::assertNotDispatched(FileIngestionProgressed::class);
});

it('hands an empty job list to the shared batch step when the file yields no chunks', function (): void {
    Storage::fake();

    $agent = Agent::factory()->create();
    $sourceFile = AgentFileFactory::new()->create([
        'status' => 'uploaded',
        'filename' => 'empty.txt',
    ]);
    Storage::put($sourceFile->filepath, '   ');
    $agent->files()->attach($sourceFile->id);

    // Completing an empty source is that step's job (DispatchSourceIngestionBatchActionTest).
    $this->mock(DispatchSourceIngestionBatchAction::class)
        ->shouldReceive('execute')
        ->once()
        ->withArgs(fn (SourceFile $source, Agent $for, Collection $jobs): bool => $source->is($sourceFile) && $for->is($agent) && $jobs->isEmpty())
        ->andReturnNull();

    itfAction(new FakeTextSplitter([]))->execute($sourceFile, $agent);

    expect($sourceFile->fresh()->parser)->toBe('fake');
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

    itfAction(FakeTextSplitter::returning('# Title', 'Body text.'))->execute($sourceFile, $agent);

    Bus::assertBatched(fn (PendingBatch $batch): bool => str_contains((string) $batch->name, (string) $sourceFile->id));
    expect($sourceFile->fresh()->status)->toBe(SourceStatus::Processing)
        ->and((int) $sourceFile->fresh()->expected_chunks)->toBe(2)
        ->and($sourceFile->fresh()->parser)->toBe('fake');
});

it('reads ebook (.epub/.fb2) text as Markdown before chunking', function (): void {
    Bus::fake();
    Storage::fake();

    $agent = Agent::factory()->create();
    $sourceFile = AgentFileFactory::new()->create([
        'status' => 'uploaded',
        'filename' => 'book.epub',
    ]);
    // Raw ebook bytes — the EbookText collaborator is faked, so the action
    // never reads them; it routes through toMarkdown() instead of getContent().
    Storage::put($sourceFile->filepath, 'epub-bytes');
    $agent->files()->attach($sourceFile->id);

    $ebookText = itfFakeEbookText("# Chapter 1\n\nThe book body.");
    itfAction(FakeTextSplitter::returning('# Chapter 1', 'The book body.'), $ebookText)
        ->execute($sourceFile, $agent);

    Bus::assertBatched(fn (PendingBatch $batch): bool => str_contains((string) $batch->name, (string) $sourceFile->id));
    expect($sourceFile->fresh()->status)->toBe(SourceStatus::Processing)
        ->and((int) $sourceFile->fresh()->expected_chunks)->toBe(2)
        ->and($sourceFile->fresh()->parser)->toBe('fake');
});

it('splits Markdown files and ebooks as Markdown, and .txt files as plain text', function (string $filename, ContentKind $kind): void {
    Bus::fake();
    Storage::fake();

    $agent = Agent::factory()->create();
    $sourceFile = AgentFileFactory::new()->create(['status' => 'uploaded', 'filename' => $filename]);
    Storage::put($sourceFile->filepath, 'Some text.');
    $agent->files()->attach($sourceFile->id);

    $splitter = FakeTextSplitter::returning('Some text.');
    itfAction($splitter, itfFakeEbookText('# Some text.'))->execute($sourceFile, $agent);

    expect($splitter->kinds)->toBe([$kind]);
})->with([
    'txt' => ['guide.txt', ContentKind::Text],
    'md' => ['notes.md', ContentKind::Markdown],
    'markdown' => ['notes.markdown', ContentKind::Markdown],
    'epub' => ['book.epub', ContentKind::Markdown],
]);

it('reads a windows-1251 FB2 book itself on a server without pandoc', function (): void {
    Bus::fake();
    Storage::fake();
    config(['services.bin.pandoc' => '/nowhere/pandoc']);

    $agent = Agent::factory()->create();
    $sourceFile = AgentFileFactory::new()->create(['status' => 'uploaded', 'filename' => 'book.fb2']);
    $xml = '<?xml version="1.0" encoding="windows-1251"?><FictionBook xmlns="http://www.gribuser.ru/xml/fictionbook/2.0">'
        .'<body><section><title><p>Глава 1</p></title><p>Весной мы сажаем картофель.</p></section></body></FictionBook>';
    Storage::put($sourceFile->filepath, mb_convert_encoding($xml, 'Windows-1251', 'UTF-8'));
    $agent->files()->attach($sourceFile->id);

    $splitter = FakeTextSplitter::returning('Глава 1');
    app()->make(IngestTextFileAction::class, ['splitter' => $splitter])->execute($sourceFile, $agent);

    expect($splitter->texts)->toBe(["## Глава 1\n\nВесной мы сажаем картофель."])
        ->and($splitter->kinds)->toBe([ContentKind::Markdown]);
});

it('splits with the built-in splitter and records its name', function (): void {
    Bus::fake();
    Storage::fake();
    config(['chatvia.ai.chunking' => ['unit' => 'characters', 'size' => 200, 'overlap' => 0, 'strategies' => ['markdown' => 'markdown']]]);

    $agent = Agent::factory()->create();
    $sourceFile = AgentFileFactory::new()->create(['status' => 'uploaded', 'filename' => 'notes.md']);
    Storage::put($sourceFile->filepath, "# Returns\n\n".str_repeat('You can return any item within 30 days. ', 6)."\n\n# Shipping\n\nWe ship in two days.");
    $agent->files()->attach($sourceFile->id);

    itfAction(new BuiltinTextSplitter)->execute($sourceFile, $agent);

    Bus::assertBatched(fn (PendingBatch $batch): bool => str_contains((string) $batch->name, (string) $sourceFile->id));
    expect($sourceFile->fresh()->parser)->toBe('builtin')
        ->and((int) $sourceFile->fresh()->expected_chunks)->toBe(3);
});
