<?php

use Database\Factories\AgentFileFactory;
use Domain\Knowledge\Source\Text\Models\SourceText;
use Domain\Knowledge\Source\Web\Models\SourceWebsite;
use Illuminate\Support\Uri;
use Tests\Support\Api\ActsAsApiOwner;

/**
 * The list of an agent's sources through the public API: every kind in one
 * list, by public id, newest first and paged by a cursor that orders two
 * sources added in the same second (the higher id first; on equal ids
 * `source_file` sorts before `source_website`). Another agent's sources
 * never show.
 */
uses(ActsAsApiOwner::class);

beforeEach(function (): void {
    $this->actAsApiOwner();
    $this->uri = "/api/v1/agents/{$this->agent->external_id}/sources";
});

it('pages the agent\'s sources of every kind together, newest first, ordering ones of the same second', function (): void {
    $text = SourceText::factory()->attachedTo($this->agent)->create(['created_at' => now()->subDays(3)]);
    $file = AgentFileFactory::new()->create(['workspace_id' => $this->agent->workspace_id, 'created_at' => now()->subDays(2)]);
    $sameSecond = now()->subDay()->startOfSecond();
    $website = SourceWebsite::factory()->attachedTo($this->agent)->ingested()->create(['created_at' => $sameSecond]);
    $secondFile = AgentFileFactory::new()->create(['workspace_id' => $this->agent->workspace_id, 'created_at' => $sameSecond]);
    $newestText = SourceText::factory()->attachedTo($this->agent)->create(['excerpt' => 'Opening hours']);
    $this->agent->files()->attach([$file->id, $secondFile->id]);
    createAgent($this->owner)->files()->attach(AgentFileFactory::new()->create(['workspace_id' => $this->agent->workspace_id])->id);

    $seen = [];
    $cursor = null;
    do {
        $page = $this->withToken($this->token)
            ->getJson(Uri::of($this->uri)->withQuery(['per_page' => 2, 'cursor' => $cursor])->value())
            ->assertOk();

        $seen = [...$seen, ...array_map(fn (array $source): string => $source['kind'].':'.$source['id'], $page->json('data'))];
        $cursor = $page->json('next_cursor');
    } while ($cursor !== null);

    $sameSecondPair = $secondFile->id >= $website->id
        ? ['file:'.$secondFile->external_id, 'url:'.$website->external_id]
        : ['url:'.$website->external_id, 'file:'.$secondFile->external_id];

    expect($seen)->toBe([
        'text:'.$newestText->external_id,
        ...$sameSecondPair,
        'file:'.$file->external_id,
        'text:'.$text->external_id,
    ]);
});
