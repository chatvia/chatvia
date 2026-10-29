<?php

use App\Identity\Workspace\Models\User;
use Database\Factories\AgentFileFactory;
use Domain\Knowledge\Ingestion\Models\Chunk;

/**
 * The agent's file list (GET agents.sources.files.data) comes one page at a
 * time, newest first, split into the files waiting to be trained and the
 * trained ones; a file that is training shows its progress from the chunks
 * stored so far, and a trained file says whether it has a summary to show.
 */
beforeEach(function (): void {
    $this->owner = User::factory()->create();
    $this->agent = createAgent($this->owner);
    $this->workspaceId = $this->owner->workspace()->id;
});

it('pages the file list, newest first, split into waiting and trained files', function (): void {
    $files = AgentFileFactory::new()->count(51)->create(['workspace_id' => $this->workspaceId, 'status' => 'ingested']);
    $uploaded = AgentFileFactory::new()->create(['workspace_id' => $this->workspaceId, 'status' => 'uploaded']);
    $this->agent->files()->attach([...$files->pluck('id')->all(), $uploaded->id]);

    $this->actingAs($this->owner)
        ->getJson(route('agents.sources.files.data', $this->agent))
        ->assertOk()
        ->assertJsonPath('total', 51)
        ->assertJsonPath('per_page', 50)
        ->assertJsonCount(50, 'data')
        ->assertJsonPath('data.0.id', $files->last()->id)
        ->assertJsonPath('data.0.progress', 100);

    $this->actingAs($this->owner)
        ->getJson(route('agents.sources.files.data', [$this->agent, 'page' => 2]))
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $files->first()->id);

    $this->actingAs($this->owner)
        ->getJson(route('agents.sources.files.data', [$this->agent, 'stage' => 'uploaded']))
        ->assertJsonPath('total', 1)
        ->assertJsonPath('data.0.id', $uploaded->id);
});

it('shows the progress of a training file from its stored chunks', function (): void {
    $file = AgentFileFactory::new()->create(['workspace_id' => $this->workspaceId, 'status' => 'ingesting', 'expected_chunks' => 4]);
    $this->agent->files()->attach($file->id);
    Chunk::factory()->forSource($file)->create();

    $this->actingAs($this->owner)
        ->getJson(route('agents.sources.files.data', $this->agent))
        ->assertJsonPath('data.0.progress', 25);
});

it('says which trained files have a summary to show', function (): void {
    [$withSummary, $withoutSummary] = AgentFileFactory::new()->count(2)->create(['workspace_id' => $this->workspaceId, 'status' => 'ingested']);
    $this->agent->files()->attach([$withSummary->id, $withoutSummary->id]);
    Chunk::factory()->forSource($withSummary)->summary()->create();
    Chunk::factory()->forSource($withoutSummary)->create();

    $this->actingAs($this->owner)
        ->getJson(route('agents.sources.files.data', $this->agent))
        ->assertJsonPath('data.0.id', $withoutSummary->id)
        ->assertJsonPath('data.0.has_summary', false)
        ->assertJsonPath('data.1.has_summary', true);
});
