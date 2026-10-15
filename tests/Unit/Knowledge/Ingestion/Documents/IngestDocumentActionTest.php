<?php

use Database\Factories\AgentFileFactory;
use Domain\Agent\Models\Agent;
use Domain\Knowledge\Ingestion\Actions\Documents\IngestDocumentAction;
use Domain\Knowledge\Ingestion\Contracts\DocumentParser;
use Domain\Knowledge\Ingestion\Contracts\TextSplitter;
use Domain\Knowledge\Ingestion\Data\Ocr\PageData;
use Domain\Knowledge\Ingestion\Services\Chunking\BuiltinTextSplitter;
use Domain\Knowledge\Source\File\Events\FileIngestionProgressed;
use Domain\Knowledge\Source\Services\KnowledgeStoragePath;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;

/**
 * IngestDocumentAction reads a document into pages once, splits long pages,
 * and hands the pages to one chunking batch. An ingested file is never parsed
 * again (parsing is the costly step), and a retry reads the stored pages back.
 *
 * The DocumentParser is faked, so the tests never reach a parsing service.
 */
function idaFakeParser(): DocumentParser
{
    return new class implements DocumentParser
    {
        public function parse(string $filePath, string $storageDirectory): Collection
        {
            return collect();
        }

        public function name(): string
        {
            return 'fake';
        }
    };
}

it('is idempotent: a SourceFile already in the ingested state returns immediately', function (): void {
    Bus::fake();
    Event::fake([FileIngestionProgressed::class]);

    $agent = Agent::factory()->create();
    $sourceFile = AgentFileFactory::new()->create([
        'status' => 'ingested',
        'mime_type' => 'application/pdf',
    ]);
    $agent->files()->attach($sourceFile->id);

    app()->make(IngestDocumentAction::class, ['parser' => idaFakeParser()])->execute($sourceFile, $agent);

    Bus::assertNothingBatched();
    Event::assertNotDispatched(FileIngestionProgressed::class);
});

it('reads the stored pages back on a retry instead of parsing the file again', function (): void {
    Bus::fake();
    Storage::fake();

    $agent = Agent::factory()->create();
    $sourceFile = AgentFileFactory::new()->create([
        'workspace_id' => $agent->workspace_id,
        'status' => 'ingesting',
        'mime_type' => 'application/pdf',
        'parser_filepath' => 'workspaces/x/sources/y/pages.json',
    ]);
    Storage::put($sourceFile->parser_filepath, collect([
        new PageData(pageNumber: 1, content: 'Stored page one', checksum: hash('xxh128', 'Stored page one')),
        new PageData(pageNumber: 2, content: 'Stored page two', checksum: hash('xxh128', 'Stored page two')),
    ])->toJson());
    $agent->files()->attach($sourceFile->id);

    $parser = new class implements DocumentParser
    {
        public int $calls = 0;

        public function parse(string $filePath, string $storageDirectory): Collection
        {
            $this->calls++;

            return collect();
        }

        public function name(): string
        {
            return 'local';
        }
    };

    app()->make(IngestDocumentAction::class, ['parser' => $parser])->execute($sourceFile, $agent);

    expect($parser->calls)->toBe(0);
    Bus::assertBatched(fn ($batch): bool => $batch->name === "Ingestion {$sourceFile->getMorphClass()} ID: {$sourceFile->id}"
        && $batch->jobs->flatMap(fn ($job): array => $job->group->pages)->pluck('content')->all() === ['Stored page one', 'Stored page two']);
});

it('reads the document with the configured parser and records the parser name', function (): void {
    Bus::fake();
    Storage::fake();

    $agent = Agent::factory()->create();
    $sourceFile = AgentFileFactory::new()->create([
        'workspace_id' => $agent->workspace_id,
        'status' => 'uploaded',
        'filename' => 'manual.pdf',
        'mime_type' => 'application/pdf',
    ]);
    Storage::put($sourceFile->filepath, '%PDF-1.4 test');
    $agent->files()->attach($sourceFile->id);

    $parser = new class implements DocumentParser
    {
        /** @var list<string> */
        public array $parsed = [];

        public function parse(string $filePath, string $storageDirectory): Collection
        {
            $this->parsed[] = $storageDirectory;

            return collect([
                new PageData(pageNumber: 1, content: 'Page one', checksum: hash('xxh128', 'Page one')),
                new PageData(pageNumber: 2, content: 'Page two', checksum: hash('xxh128', 'Page two')),
            ]);
        }

        public function name(): string
        {
            return 'local';
        }
    };

    app()->make(IngestDocumentAction::class, ['parser' => $parser])->execute($sourceFile, $agent);

    $fresh = $sourceFile->fresh();

    $folder = KnowledgeStoragePath::forSource($sourceFile);

    expect($parser->parsed)->toBe([$folder.'/tmp'])
        ->and($fresh->parser)->toBe('local')
        ->and((int) $fresh->expected_chunks)->toBe(2)
        ->and($fresh->parser_filepath)->toStartWith($folder.'/');

    Storage::assertExists($fresh->parser_filepath);
    // Plain pages share one chunk job.
    Bus::assertBatched(fn ($batch): bool => $batch->jobs->count() === 1 && count($batch->jobs->first()->group->pages) === 2);
});

it('reads a file again with another document reader instead of reusing the cached pages', function (): void {
    Bus::fake();
    $agent = Agent::factory()->create();

    $parserNamed = fn (string $name): DocumentParser => new class($name) implements DocumentParser
    {
        public int $calls = 0;

        public function __construct(private readonly string $readerName) {}

        public function parse(string $filePath, string $storageDirectory): Collection
        {
            $this->calls++;

            return collect([new PageData(pageNumber: 1, content: 'Page one', checksum: hash('xxh128', 'Page one'))]);
        }

        public function name(): string
        {
            return $this->readerName;
        }
    };

    foreach (['local', 'mistral-ocr-latest'] as $name) {
        $sourceFile = AgentFileFactory::new()->create([
            'workspace_id' => $agent->workspace_id,
            'status' => 'uploaded',
            'filename' => 'manual.pdf',
            'mime_type' => 'application/pdf',
        ]);
        Storage::put($sourceFile->filepath, '%PDF-1.4 same bytes');

        $parser = $parserNamed($name);
        app()->make(IngestDocumentAction::class, ['parser' => $parser])->execute($sourceFile, $agent);

        expect($parser->calls)->toBe(1);
    }
});

it('splits a page longer than the chunk size into parts that keep its page number', function (): void {
    Bus::fake();
    Storage::fake();
    config(['chatvia.ai.chunking' => ['unit' => 'characters', 'size' => 200, 'overlap' => 0, 'strategies' => ['text' => 'recursive']]]);
    app()->instance(TextSplitter::class, new BuiltinTextSplitter);

    $agent = Agent::factory()->create();
    $sourceFile = AgentFileFactory::new()->create(['workspace_id' => $agent->workspace_id, 'status' => 'uploaded', 'filename' => 'manual.pdf', 'mime_type' => 'application/pdf']);
    Storage::put($sourceFile->filepath, '%PDF-1.4 test');
    $agent->files()->attach($sourceFile->id);

    $long = str_repeat('Every tent is tested on a real trip before we sell it. ', 8);

    $parser = new readonly class($long) implements DocumentParser
    {
        public function __construct(private string $long) {}

        public function parse(string $filePath, string $storageDirectory): Collection
        {
            return collect([
                new PageData(pageNumber: 1, content: 'A short first page.', checksum: hash('xxh128', 'A short first page.')),
                new PageData(pageNumber: 2, content: $this->long, checksum: hash('xxh128', $this->long), tables: ['| a |']),
            ]);
        }

        public function name(): string
        {
            return 'local';
        }
    };

    app()->make(IngestDocumentAction::class, ['parser' => $parser])->execute($sourceFile, $agent);

    Bus::assertBatched(function ($batch): bool {
        $pages = $batch->jobs->flatMap(fn ($job): array => $job->group->pages);

        return $pages->count() === 4
            && $pages->pluck('pageNumber')->all() === [1, 2, 2, 2]
            && $pages->every(fn (PageData $page): bool => mb_strlen($page->content) <= 200);
    });

    // The stored pages stay whole.
    expect(PageData::fromJson(Storage::get($sourceFile->fresh()->parser_filepath)))->toHaveCount(2);
});

it('gives the media of a split page to its first part only', function (): void {
    $page = new PageData(pageNumber: 7, content: 'Long page', checksum: 'x', imagePaths: ['a.png'], tables: ['| a |'], hyperlinks: ['https://x.test']);

    [$first, $second] = $page->partsOf(['Long', 'page']);

    expect([$first->pageNumber, $second->pageNumber])->toBe([7, 7])
        ->and($first->imagePaths)->toBe(['a.png'])
        ->and($first->tables)->toBe(['| a |'])
        ->and($second->imagePaths)->toBe([])
        ->and($second->tables)->toBe([])
        ->and($first->checksum)->not->toBe($second->checksum)
        ->and($page->partsOf(['Long page']))->toBe([$page]);
});
