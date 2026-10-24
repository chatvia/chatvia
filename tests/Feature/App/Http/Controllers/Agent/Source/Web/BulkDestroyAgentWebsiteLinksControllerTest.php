<?php

use App\Identity\Workspace\Models\User;
use Domain\Knowledge\Source\Web\Models\SourceWebsite;

/**
 * POST agents.sources.websites.links.bulk-destroy removes several pages of
 * one website in one call. A page of another website, an unknown id or more
 * than 500 ids at once is a validation error, and nothing is removed.
 */
beforeEach(function (): void {
    $this->owner = User::factory()->create();
    $this->agent = createAgent($this->owner);
    $this->website = SourceWebsite::factory()->ingested()->attachedTo($this->agent)->create(['url' => 'https://docs.example.test']);
});

it('removes several pages of the website in one call', function (): void {
    $removed = $this->website->urls()->create(['url' => 'https://docs.example.test/a']);
    $kept = $this->website->urls()->create(['url' => 'https://docs.example.test/b']);

    $this->actingAs($this->owner)
        ->postJson(route('agents.sources.websites.links.bulk-destroy', [$this->agent, $this->website]), ['link_ids' => [$removed->id, $removed->id]])
        ->assertOk()
        ->assertJsonPath('count', 1);

    expect($this->website->urls()->pluck('id')->all())->toBe([$kept->id]);
});

it('refuses to remove a page of another website or too many pages at once', function (array $linkIds): void {
    $own = $this->website->urls()->create(['url' => 'https://docs.example.test/a']);
    $other = SourceWebsite::factory()->ingested()->create(['workspace_id' => $this->agent->workspace_id, 'url' => 'https://other.example.test']);
    $foreign = $other->urls()->create(['url' => 'https://other.example.test/a']);

    $ids = array_map(fn (string $id): int => match ($id) {
        'own' => $own->id,
        'foreign' => $foreign->id,
        default => (int) $id,
    }, $linkIds);

    $this->actingAs($this->owner)
        ->postJson(route('agents.sources.websites.links.bulk-destroy', [$this->agent, $this->website]), ['link_ids' => $ids])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('link_ids');

    expect($this->website->urls()->whereKey($own->id)->exists())->toBeTrue()
        ->and($other->urls()->whereKey($foreign->id)->exists())->toBeTrue();
})->with([
    'a page of another website' => [['own', 'foreign']],
    'an unknown id' => [['own', '999999999']],
    'more than 500 ids' => [['own', ...array_map(strval(...), range(1, 500))]],
]);
