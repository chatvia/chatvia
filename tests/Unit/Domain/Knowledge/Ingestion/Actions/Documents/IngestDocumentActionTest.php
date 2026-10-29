<?php

use Database\Factories\AgentFileFactory;
use Domain\Agent\Models\Agent;
use Domain\Knowledge\Ingestion\Actions\Documents\IngestDocumentAction;
use Domain\Knowledge\Ingestion\Data\Ocr\PageData;
use Domain\Knowledge\Ingestion\Jobs\Document\DocumentChunkIngestionJob;
use Domain\Knowledge\Source\Services\KnowledgeStoragePath;
use Illuminate\Bus\PendingBatch;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;
use Tests\Support\Knowledge\Ingestion\FakeDocumentParser;

/**
 * IngestDocumentAction reads a document into pages once and hands the pages
 * to one chunking batch. A retry reads the stored pages back instead of
 * parsing the file again (parsing is the costly step).
 *
 * The DocumentParser is faked, so the tests never reach a parsing service.
 */
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

    $parser = FakeDocumentParser::reading('local', 'Page one', 'Page two');

    app()->make(IngestDocumentAction::class, ['parser' => $parser])->execute($sourceFile, $agent);

    $fresh = $sourceFile->fresh();
    $folder = KnowledgeStoragePath::forSource($sourceFile);

    expect($parser->directories)->toBe([$folder.'/tmp'])
        ->and($fresh->parser)->toBe('local')
        ->and((int) $fresh->expected_chunks)->toBe(2)
        ->and($fresh->parser_filepath)->toStartWith($folder.'/');

    Storage::assertExists($fresh->parser_filepath);
    Bus::assertBatched(fn (PendingBatch $batch): bool => $batch->jobs->count() === 1 && count($batch->jobs->first()->group->pages) === 2);
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

    $parser = FakeDocumentParser::reading('local');

    app()->make(IngestDocumentAction::class, ['parser' => $parser])->execute($sourceFile, $agent);

    expect($parser->calls)->toBe(0);
    Bus::assertBatched(fn (PendingBatch $batch): bool => $batch->name === "Ingestion {$sourceFile->getMorphClass()} ID: {$sourceFile->id}"
        && $batch->jobs->flatMap(fn (DocumentChunkIngestionJob $job): array => $job->group->pages)->pluck('content')->all() === ['Stored page one', 'Stored page two']);
});
