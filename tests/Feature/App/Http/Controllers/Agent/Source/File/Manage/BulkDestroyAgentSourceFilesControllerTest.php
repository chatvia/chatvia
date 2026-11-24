<?php

use App\Identity\Workspace\Models\User;
use Database\Factories\AgentFileFactory;
use Domain\Knowledge\Source\File\Models\SourceFile;

/**
 * POST agents.sources.files.bulk-destroy removes several of the agent's files
 * in one call: a file another agent also uses is unlinked, the rest are
 * deleted. A file the agent does not use is a validation error, and nothing
 * is removed.
 */
beforeEach(function (): void {
    $this->owner = User::factory()->create();
    $this->agent = createAgent($this->owner);
});

it('deletes the agent\'s own files and unlinks the shared ones in one call', function (): void {
    $other = createAgent($this->owner);
    $own = AgentFileFactory::new()->create(['workspace_id' => $this->agent->workspace_id]);
    $shared = AgentFileFactory::new()->create(['workspace_id' => $this->agent->workspace_id]);
    $kept = AgentFileFactory::new()->create(['workspace_id' => $this->agent->workspace_id]);
    $this->agent->files()->attach([$own->id, $shared->id, $kept->id]);
    $other->files()->attach($shared->id);

    $this->actingAs($this->owner)
        ->postJson(route('agents.sources.files.bulk-destroy', $this->agent), ['file_ids' => [$own->id, $shared->id, $own->id]])
        ->assertOk()
        ->assertJsonPath('count', 2);

    expect($this->agent->files()->pluck('source_files.id')->all())->toBe([$kept->id])
        ->and(SourceFile::query()->whereKey($own->id)->exists())->toBeFalse()
        ->and($other->files()->whereKey($shared->id)->exists())->toBeTrue();
});

it('refuses a file the agent does not use and removes nothing', function (): void {
    $own = AgentFileFactory::new()->create(['workspace_id' => $this->agent->workspace_id]);
    $this->agent->files()->attach($own->id);
    $foreign = AgentFileFactory::new()->create(['workspace_id' => $this->agent->workspace_id]);
    createAgent($this->owner)->files()->attach($foreign->id);

    $this->actingAs($this->owner)
        ->postJson(route('agents.sources.files.bulk-destroy', $this->agent), ['file_ids' => [$own->id, $foreign->id]])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('file_ids');

    expect($this->agent->files()->whereKey($own->id)->exists())->toBeTrue()
        ->and(SourceFile::query()->whereKey($foreign->id)->exists())->toBeTrue();
});
