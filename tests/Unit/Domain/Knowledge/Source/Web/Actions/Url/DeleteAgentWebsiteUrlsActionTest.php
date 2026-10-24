<?php

use Domain\Agent\Models\Agent;
use Domain\Knowledge\Ingestion\Models\Chunk;
use Domain\Knowledge\Source\Web\Actions\Url\DeleteAgentWebsiteUrlsAction;
use Domain\Knowledge\Source\Web\Models\SourceWebsite;

/**
 * Removing several pages of a website at once: the chunks only they produced
 * go with them, a chunk another page still uses stays, and a page of another
 * website is left alone.
 */
it('removes several URLs with the chunks only they produced', function (): void {
    $agent = Agent::factory()->create();
    $website = SourceWebsite::factory()->ingested()->attachedTo($agent)->create(['url' => 'https://docs.example.test']);
    $removedA = $website->urls()->create(['url' => 'https://docs.example.test/a']);
    $removedB = $website->urls()->create(['url' => 'https://docs.example.test/b']);
    $kept = $website->urls()->create(['url' => 'https://docs.example.test/c']);

    $onlyA = Chunk::factory()->forSource($website)->referencedBy($removedA)->create(['chunk' => 'Only on page A.']);
    $shared = Chunk::factory()->forSource($website)->referencedBy($removedB, $kept)->create(['chunk' => 'On pages B and C.']);

    $foreign = SourceWebsite::factory()->ingested()->attachedTo($agent)->create(['url' => 'https://other.example.test']);
    $foreignUrl = $foreign->urls()->create(['url' => 'https://docs.example.test/x']);

    $count = app(DeleteAgentWebsiteUrlsAction::class)->execute($website, [$removedA->id, $removedB->id, $foreignUrl->id]);

    expect($count)->toBe(2)
        ->and($website->urls()->pluck('url')->all())->toBe(['https://docs.example.test/c'])
        ->and(Chunk::query()->find($onlyA->id))->toBeNull()
        ->and(Chunk::query()->find($shared->id))->not->toBeNull()
        ->and($foreignUrl->fresh())->not->toBeNull();
});
