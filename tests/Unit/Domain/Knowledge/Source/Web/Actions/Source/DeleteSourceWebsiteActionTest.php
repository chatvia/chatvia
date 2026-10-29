<?php

use Domain\Agent\Models\Agent;
use Domain\Knowledge\Ingestion\Models\Chunk;
use Domain\Knowledge\Source\Services\KnowledgeStoragePath;
use Domain\Knowledge\Source\Web\Actions\Source\DeleteSourceWebsiteAction;
use Domain\Knowledge\Source\Web\Models\SourceWebsite;
use Illuminate\Support\Facades\Storage;

/**
 * Deleting a website removes its row, its chunks, its agent links and its
 * folder in storage.
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
