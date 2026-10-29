<?php

use Database\Factories\AgentFileFactory;
use Domain\Agent\Models\Agent;
use Domain\Knowledge\Document\Conversion\EbookText;
use Domain\Knowledge\Ingestion\Actions\Documents\IngestTextFileAction;
use Domain\Knowledge\Ingestion\Contracts\TextSplitter;
use Domain\Knowledge\Ingestion\Enums\ContentKind;
use Domain\Knowledge\Source\Enums\SourceStatus;
use Illuminate\Bus\PendingBatch;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\Support\Knowledge\Ingestion\FakeTextSplitter;

/**
 * IngestTextFileAction chunks a raw-text SourceFile (.txt/.md/ebook) directly
 * with the text splitter (no OCR), as Markdown or plain text, and rides the
 * shared DocumentChunkIngestionJob batch. The splitter is faked to return
 * fixed chunks (FakeTextSplitter::returning()), so these check the
 * orchestration: real-chunk fan-out and the kind of content.
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

    Bus::assertBatched(fn (PendingBatch $batch): bool => Str::contains((string) $batch->name, (string) $sourceFile->id));
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
    'epub' => ['book.epub', ContentKind::Markdown],
]);
