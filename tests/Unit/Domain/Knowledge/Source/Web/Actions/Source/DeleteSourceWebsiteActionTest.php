<?php

use Domain\Agent\Models\Agent;
use Domain\Knowledge\Ingestion\Models\Chunk;
use Domain\Knowledge\Source\Services\KnowledgeStoragePath;
use Domain\Knowledge\Source\Web\Actions\Source\DeleteSourceWebsiteAction;
use Domain\Knowledge\Source\Web\Models\SourceWebsite;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Deleting a website removes its row, its chunks, its agent links and its
 * folder in storage. The chunks go in batches before the transaction that
 * deletes the row, so a big website does not hold one long transaction.
 */
it('deletes a website with its chunks, agent links and stored folder', function (): void {
    Storage::fake();
    $agent = Agent::factory()->create();
    $website = SourceWebsite::factory()->ingested()->attachedTo($agent)->create();
    $document = KnowledgeStoragePath::forSource($website).'/website.md';
    Storage::put($document, 'The website as one document.');
    $website->update(['filepath' => $document]);
    Chunk::factory()->forSource($website)->create();

    app(DeleteSourceWebsiteAction::class)->execute($website);

    expect(SourceWebsite::query()->find($website->id))->toBeNull()
        ->and(Chunk::query()->ofSource($website)->exists())->toBeFalse()
        ->and($agent->websites()->count())->toBe(0)
        ->and(Storage::allFiles())->toBe([]);
});

it('deletes a website chunks before the transaction that deletes the row', function (): void {
    $agent = Agent::factory()->create();
    $website = SourceWebsite::factory()->ingested()->attachedTo($agent)->create();
    Chunk::factory()->forSource($website)->count(2)->create();

    $outerLevel = DB::transactionLevel();
    $chunkDeleteLevels = [];
    DB::listen(function (QueryExecuted $query) use (&$chunkDeleteLevels): void {
        if (Str::startsWith($query->sql, 'DELETE FROM chunks ')) {
            $chunkDeleteLevels[] = $query->connection->transactionLevel();
        }
    });

    app(DeleteSourceWebsiteAction::class)->execute($website);

    // The batches run outside the action's transaction; the `deleting` hook
    // inside it finds nothing left.
    expect($chunkDeleteLevels[0])->toBe($outerLevel)
        ->and(SourceWebsite::query()->find($website->id))->toBeNull()
        ->and(Chunk::query()->ofSource($website)->exists())->toBeFalse();
});
