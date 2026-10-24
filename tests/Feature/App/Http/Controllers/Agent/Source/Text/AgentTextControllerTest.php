<?php

use App\Identity\Workspace\Models\User;
use Domain\Knowledge\Ingestion\Jobs\Text\TextIngestionJob;
use Domain\Knowledge\Ingestion\Models\Chunk;
use Domain\Knowledge\Source\Text\Models\SourceText;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * The agent's text list comes one page at a time (newest first) and reads the
 * rows only: a text's content stays in storage, and only a text that is
 * training counts its chunks for the progress bar. Saving a text the
 * workspace already has is a validation error, not a server error.
 */
beforeEach(function (): void {
    $this->owner = User::factory()->create();
    $this->agent = createAgent($this->owner);
});

it('pages the text list from the rows, without reading the texts from storage', function (): void {
    Storage::fake();
    SourceText::factory()->ingested()->attachedTo($this->agent)->count(51)->create();
    $training = SourceText::factory()->processing()->attachedTo($this->agent)->create(['expected_chunks' => 2]);
    Chunk::factory()->forSource($training)->create();

    $response = $this->actingAs($this->owner)
        ->getJson(route('agents.sources.text.index', $this->agent))
        ->assertOk()
        ->assertJsonPath('total', 52)
        ->assertJsonCount(50, 'data')
        ->assertJsonPath('data.0.id', $training->id)
        ->assertJsonPath('data.0.progress', 50)
        ->assertJsonPath('data.0.agents_count', 1);

    expect($response->json('data.0'))->not->toHaveKeys(['filepath', 'word_count']);
});

it('answers a text the workspace already has with a validation error', function (): void {
    Bus::fake([TextIngestionJob::class]);
    Storage::fake();
    $content = Str::repeat('Our refund policy covers every purchase made in the last thirty days. ', 10);
    $other = createAgent($this->owner);

    $this->actingAs($this->owner)
        ->postJson(route('agents.sources.text.store', $other), ['content' => $content])
        ->assertOk();

    $this->actingAs($this->owner)
        ->postJson(route('agents.sources.text.store', $this->agent), ['content' => $content])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['content' => 'This text is already in your knowledge base.']);
});
