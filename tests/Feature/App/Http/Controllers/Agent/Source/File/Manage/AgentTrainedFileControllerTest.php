<?php

use App\Identity\Workspace\Models\User;
use Database\Factories\AgentFileFactory;

/**
 * The workspace's trained files, offered to link to another agent (GET
 * agents.sources.list), and unlinking one from the agent (POST
 * agents.sources.files.unlink): a file other agents use is unlinked, its last
 * link is refused (UnlinkSourceFileFromAgentActionTest has the rule).
 */
beforeEach(function (): void {
    $this->owner = User::factory()->create();
    $this->agent = createAgent($this->owner);
});

it('lists the workspace\'s trained files of a type, to link them', function (): void {
    $file = AgentFileFactory::new()->create(['workspace_id' => $this->owner->workspace()->id, 'status' => 'ingested', 'filetype' => 'pdf']);

    $this->actingAs($this->owner)
        ->getJson(route('agents.sources.list', [$this->agent, 'type' => 'pdf']))
        ->assertOk()
        ->assertJsonPath('0.id', $file->id);
});

it('unlinks a shared file from the agent and refuses its last link', function (): void {
    $other = createAgent($this->owner);
    $shared = AgentFileFactory::new()->create(['workspace_id' => $this->owner->workspace()->id]);
    $this->agent->files()->attach($shared->id);
    $other->files()->attach($shared->id);
    $onlyHere = AgentFileFactory::new()->create(['workspace_id' => $this->owner->workspace()->id]);
    $this->agent->files()->attach($onlyHere->id);

    $this->actingAs($this->owner)
        ->postJson(route('agents.sources.files.unlink', [$this->agent, $shared->id]))
        ->assertOk();

    $this->actingAs($this->owner)
        ->postJson(route('agents.sources.files.unlink', [$this->agent, $onlyHere->id]))
        ->assertStatus(400);

    expect($this->agent->files()->whereKey($shared->id)->exists())->toBeFalse()
        ->and($other->files()->whereKey($shared->id)->exists())->toBeTrue()
        ->and($this->agent->files()->whereKey($onlyHere->id)->exists())->toBeTrue();
});
