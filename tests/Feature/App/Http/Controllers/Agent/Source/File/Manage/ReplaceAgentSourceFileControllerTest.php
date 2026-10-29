<?php

use App\Identity\Workspace\Models\User;
use Database\Factories\AgentFileFactory;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

/**
 * POST sources/files/{file}/replace puts a new version in place of one of
 * the agent's files; a teammate without the file-training permission is
 * refused.
 */
beforeEach(function (): void {
    Storage::fake();
    Queue::fake();
    $this->owner = User::factory()->create();
    $this->agent = createAgent($this->owner);
});

it('replaces one of the agent\'s files', function (): void {
    $file = AgentFileFactory::new()->create(['workspace_id' => $this->agent->workspace_id, 'filename' => 'old.txt']);
    $this->agent->files()->attach($file->id);

    $this->actingAs($this->owner)
        ->post(route('agents.sources.files.replace', ['agent' => $this->agent, 'file' => $file->id]), [
            'file' => UploadedFile::fake()->createWithContent('new.txt', 'New prices.'),
        ])
        ->assertOk()
        ->assertJson(['id' => $file->id]);

    expect($file->fresh()->filename)->toBe('new.txt');
});

it('refuses a teammate who may not train files and keeps the old version', function (): void {
    $file = AgentFileFactory::new()->create(['workspace_id' => $this->agent->workspace_id, 'filename' => 'old.txt']);
    $this->agent->files()->attach($file->id);
    $viewer = teammateWithPermissions($this->owner, ['agent.chat.view']);

    $this->actingAs($viewer)
        ->post(route('agents.sources.files.replace', ['agent' => $this->agent, 'file' => $file->id]), [
            'file' => UploadedFile::fake()->createWithContent('new.txt', 'New prices.'),
        ])
        ->assertForbidden();

    expect($file->fresh()->filename)->toBe('old.txt');
});
