<?php

use Domain\Knowledge\Source\Text\Models\SourceText;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\Support\Api\ActsAsApiOwner;

/**
 * An agent's text sources through the public API: adding one answers 201
 * with its public id. A delete finds the text through the agent's own
 * sources, so another agent's text is a 404 even inside the same workspace.
 */
uses(ActsAsApiOwner::class);

beforeEach(function (): void {
    Storage::fake();
    Bus::fake();
    $this->actAsApiOwner();
    $this->uri = "/api/v1/agents/{$this->agent->external_id}/sources/texts";
});

it('adds a text and answers 201 with its public id', function (): void {
    $response = $this->withToken($this->token)
        ->postJson($this->uri, ['content' => Str::repeat('Refunds take five business days. ', 20)])
        ->assertCreated()
        ->assertJsonPath('data.kind', 'text');

    expect($response->json('data.id'))->toBeUuid()
        ->and(SourceText::query()->where('external_id', $response->json('data.id'))->exists())->toBeTrue();
});

it('404s a text of another agent of the same workspace and keeps it', function (): void {
    $text = SourceText::factory()->attachedTo(createAgent($this->owner))->create();

    $this->withToken($this->token)->deleteJson("{$this->uri}/{$text->external_id}")->assertNotFound();

    expect(SourceText::query()->whereKey($text->id)->exists())->toBeTrue();
});
