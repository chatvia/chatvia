<?php

use App\Identity\Workspace\Models\User;
use Database\Factories\AgentFileFactory;
use Illuminate\Support\Facades\Storage;

/**
 * GET external/files/{type}/{externalId} streams a stored knowledge file to
 * the workspace that owns it, teammates included. SourceFilePolicy::view()
 * decides (SourceFilePolicyTest); the controller answers 404, never 403, so
 * the endpoint does not confirm a file exists. Another workspace's file is
 * the isolation sweep's (CrossWorkspaceRouteTest).
 */
beforeEach(function (): void {
    Storage::fake();

    $this->owner = User::factory()->create();
    $this->file = AgentFileFactory::new()->create(['workspace_id' => $this->owner->workspace()->id, 'filename' => 'doc.txt']);
    Storage::put($this->file->filepath, 'file body');
});

it('streams the file to the owning workspace and its teammates', function (string $who): void {
    $viewer = $who === 'owner' ? $this->owner : User::factory()->employee($this->owner)->create();

    $response = $this->actingAs($viewer)
        ->get(route('external.source.files.view', ['type' => 'file', 'externalId' => $this->file->external_id]))
        ->assertOk();

    expect($response->streamedContent())->toBe('file body');
})->with(['owner', 'teammate']);

it('returns 404 to a platform admin of another workspace', function (): void {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->get(route('external.source.files.view', ['type' => 'file', 'externalId' => $this->file->external_id]))
        ->assertNotFound();
});

it('returns 404 for an unknown type or malformed id', function (string $type, ?string $externalId): void {
    $this->actingAs($this->owner)
        ->get(route('external.source.files.view', ['type' => $type, 'externalId' => $externalId ?? $this->file->external_id]))
        ->assertNotFound();
})->with([
    'an unknown type' => ['nope', null],
    'a malformed id' => ['file', 'not-a-uuid'],
]);
