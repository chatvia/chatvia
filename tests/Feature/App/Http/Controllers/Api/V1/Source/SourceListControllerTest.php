<?php

use Database\Factories\AgentFileFactory;
use Domain\Knowledge\Source\Text\Models\SourceText;
use Domain\Knowledge\Source\Web\Models\SourceWebsite;
use Illuminate\Pagination\Cursor;
use Illuminate\Support\Uri;
use Tests\Support\Api\ActsAsApiOwner;

/**
 * The list of an agent's sources through the public API: every kind in one
 * list, by public id, newest first and paged by a cursor that orders two
 * sources added in the same second. A cursor the list did not hand out
 * starts again at the first page. Another agent's sources never show.
 */
uses(ActsAsApiOwner::class);

beforeEach(function (): void {
    $this->actAsApiOwner();
    $this->uri = "/api/v1/agents/{$this->agent->external_id}/sources";
});

it('lists the sources by their public ids', function (): void {
    $file = AgentFileFactory::new()->create(['workspace_id' => $this->agent->workspace_id]);
    $this->agent->files()->attach($file->id);

    $this->withToken($this->token)->getJson($this->uri)
        ->assertOk()
        ->assertJsonPath('data.0.id', $file->external_id)
        ->assertJsonPath('data.0.kind', 'file')
        ->assertJsonPath('data.0.mime_type', $file->mime_type)
        ->assertJsonPath('data.0.length_pages', null);
});

it('pages the sources of every kind together, newest first', function (): void {
    $text = SourceText::factory()->attachedTo($this->agent)->create(['created_at' => now()->subDays(3)]);
    $file = AgentFileFactory::new()->create(['workspace_id' => $this->agent->workspace_id, 'created_at' => now()->subDays(2)]);
    // Two sources added in the same second: the cursor still orders them.
    $sameSecond = now()->subDay()->startOfSecond();
    $website = SourceWebsite::factory()->attachedTo($this->agent)->ingested()->create(['created_at' => $sameSecond]);
    $secondFile = AgentFileFactory::new()->create(['workspace_id' => $this->agent->workspace_id, 'created_at' => $sameSecond]);
    $newestText = SourceText::factory()->attachedTo($this->agent)->create(['excerpt' => 'Opening hours']);
    $this->agent->files()->attach([$file->id, $secondFile->id]);

    // A source of another agent of the same workspace is not listed.
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

    // Same second: the higher id first; on equal ids `source_file` sorts
    // before `source_website`.
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

it('starts again at the first page for a cursor it did not hand out', function (Closure $cursor): void {
    $file = AgentFileFactory::new()->create(['workspace_id' => $this->agent->workspace_id]);
    $this->agent->files()->attach($file->id);

    $this->withToken($this->token)
        ->getJson(Uri::of($this->uri)->withQuery(['cursor' => $cursor()])->value())
        ->assertOk()
        ->assertJsonPath('data.0.id', $file->external_id);
})->with([
    'a timestamp that is not one' => [fn (): string => new Cursor(['created_at' => 'yesterday', 'sourceable_id' => 1, 'sourceable_type' => 'source_file'])->encode()],
    'columns of another list' => [fn (): string => new Cursor(['id' => 1])->encode()],
]);
