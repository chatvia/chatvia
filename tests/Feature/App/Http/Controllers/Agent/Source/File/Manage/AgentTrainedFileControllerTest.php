<?php

use App\Identity\Workspace\Models\User;
use Database\Factories\AgentFileFactory;

/**
 * Unlinking a trained file from the agent (POST agents.sources.files.unlink):
 * a file other agents use is unlinked, its last link is refused.
 */
beforeEach(function (): void {
    $this->owner = User::factory()->create();
    $this->agent = createAgent($this->owner);
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
