<?php

use Domain\Agent\Models\Agent;
use Domain\Knowledge\Ingestion\Models\Chunk;
use Domain\Knowledge\Ingestion\Models\ChunkReference;
use Domain\Knowledge\Source\Enums\SourceStatus;
use Domain\Knowledge\Source\Web\Actions\Url\DeleteAgentWebsiteUrlAction;
use Domain\Knowledge\Source\Web\Models\AgentWebsiteUrl;
use Domain\Knowledge\Source\Web\Models\SourceWebsite;

/**
 * Deleting one page of a website keeps the chunks that another page of the
 * site still uses.
 */
it('deletes only the chunks no other URL still uses', function (): void {
    $agent = Agent::factory()->create();
    $website = SourceWebsite::factory()->attachedTo($agent)->create(['url' => 'https://docs.example.test', 'status' => SourceStatus::Crawling]);

    $deleted = $website->urls()->create(['url' => 'https://docs.example.test/a']);
    $kept = $website->urls()->create(['url' => 'https://docs.example.test/b']);

    $own = Chunk::factory()->forSource($website)->referencedBy($deleted)->create(['chunk' => 'Only on page A']);
    $shared = Chunk::factory()->forSource($website)->referencedBy($deleted, $kept)->create(['chunk' => 'Footer shared by A and B']);

    app(DeleteAgentWebsiteUrlAction::class)->execute($deleted);

    expect(AgentWebsiteUrl::query()->find($deleted->id))->toBeNull()
        ->and(Chunk::query()->find($own->id))->toBeNull()
        ->and(Chunk::query()->find($shared->id))->not->toBeNull()
        ->and($kept->chunks()->pluck('chunks.id')->all())->toBe([$shared->id])
        ->and(ChunkReference::query()->where('referenceable_id', $deleted->id)->exists())->toBeFalse();
});
