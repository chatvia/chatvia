<?php

use Domain\Knowledge\Source\Web\Events\WebsiteCrawlStarted;
use Domain\Knowledge\Source\Web\Models\SourceWebsite;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Support\Http\SafeUrl;
use Tests\Support\Api\ActsAsApiOwner;

/**
 * An agent's website sources through the public API: adding one answers 201
 * with its public id, and a delete takes that id.
 */
uses(ActsAsApiOwner::class);

beforeEach(function (): void {
    Storage::fake();
    Bus::fake();
    $this->actAsApiOwner();
    $this->uri = "/api/v1/agents/{$this->agent->external_id}/sources/urls";
});

afterEach(function (): void {
    SafeUrl::resolveHostsUsing(null);
});

it('adds a website and answers 201 with its public id', function (): void {
    Event::fake([WebsiteCrawlStarted::class]);
    Http::fake(['https://docs.example.test/*' => Http::response('<html></html>', 200)]);
    SafeUrl::resolveHostsUsing(fn (): array => ['93.184.216.34']);

    $response = $this->withToken($this->token)
        ->postJson($this->uri, ['url' => 'https://docs.example.test/start'])
        ->assertCreated()
        ->assertJsonPath('data.kind', 'url')
        ->assertJsonPath('data.name', 'https://docs.example.test/start');

    expect(SourceWebsite::query()->where('external_id', $response->json('data.id'))->exists())->toBeTrue();
});

it('deletes a website by its public id', function (): void {
    $website = SourceWebsite::factory()->attachedTo($this->agent)->ingested()->create();

    $this->withToken($this->token)->deleteJson("{$this->uri}/{$website->external_id}")->assertNoContent();

    expect($this->agent->websites()->exists())->toBeFalse();
});
