<?php

use App\Identity\Workspace\Models\User;
use Database\Factories\AgentFileFactory;
use Domain\Knowledge\Source\File\Models\SourceFile;

/**
 * DELETE agents.sources.files.delete deletes a file of the agent. A file of
 * the same workspace that the agent does not use is a 404 and stays (the
 * isolation sweep covers other workspaces, not other agents).
 */
it('does not delete a file the agent does not use', function (): void {
    $owner = User::factory()->create();
    $agent = createAgent($owner);
    $file = AgentFileFactory::new()->create(['workspace_id' => $owner->workspace()->id]);

    $this->actingAs($owner)
        ->deleteJson(route('agents.sources.files.delete', [$agent, $file->id]))
        ->assertNotFound();

    expect(SourceFile::query()->find($file->id))->not->toBeNull();
});
