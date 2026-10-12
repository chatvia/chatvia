<?php

use App\Identity\Workspace\Models\User;
use Domain\Knowledge\Source\File\Models\SourceFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Pins the unified authorization rule for knowledge-file viewing:
 * SourceFilePolicy::view() is the single source of truth (workspace
 * ownership, teammates included) and SourceFileViewController delegates
 * to it — 404, never 403, so the endpoint does not confirm existence.
 */
function ownedSourceFile(User $owner): SourceFile
{
    // Created directly (not via AgentFileFactory) so the owner is explicit.
    $file = SourceFile::create([
        'workspace_id' => $owner->workspace()->id,
        'external_id' => (string) Str::uuid(),
        'filename' => 'doc.txt',
        'filetype' => 'pdf',
        'filepath' => 'test/files/'.Str::uuid().'.txt',
        'size_in_kb' => 10,
        'mime_type' => 'text/plain',
        'checksum' => md5('doc'),
        'status' => 'uploaded',
        'length' => 3,
    ]);

    Storage::put($file->filepath, 'file body');

    return $file;
}

beforeEach(function (): void {
    Storage::fake();

    $this->owner = User::factory()->create();
    $this->file = ownedSourceFile($this->owner);
});

it('serves the file to the owning workspace', function (): void {
    $this->actingAs($this->owner)
        ->get(route('external.source.files.view', ['type' => 'file', 'externalId' => $this->file->external_id]))
        ->assertOk();
});

it('serves the file to a teammate of the owning workspace', function (): void {
    $teammate = User::factory()->employee($this->owner)->create();

    $this->actingAs($teammate)
        ->get(route('external.source.files.view', ['type' => 'file', 'externalId' => $this->file->external_id]))
        ->assertOk();
});

it('returns 404 to a platform admin of another workspace', function (): void {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->get(route('external.source.files.view', ['type' => 'file', 'externalId' => $this->file->external_id]))
        ->assertNotFound();
});

it('returns 404 — not 403 — for another workspace', function (): void {
    $stranger = User::factory()->create();

    $this->actingAs($stranger)
        ->get(route('external.source.files.view', ['type' => 'file', 'externalId' => $this->file->external_id]))
        ->assertNotFound();
});

it('redirects guests to login', function (): void {
    $this->get(route('external.source.files.view', ['type' => 'file', 'externalId' => $this->file->external_id]))
        ->assertRedirect(route('login'));
});

it('returns 404 for an unknown type or malformed id', function (): void {
    $this->actingAs($this->owner)
        ->get(route('external.source.files.view', ['type' => 'nope', 'externalId' => $this->file->external_id]))
        ->assertNotFound();

    $this->actingAs($this->owner)
        ->get(route('external.source.files.view', ['type' => 'file', 'externalId' => 'not-a-uuid']))
        ->assertNotFound();
});

it('applies the workspace rule to every SourceFilePolicy ability', function (): void {
    $teammate = User::factory()->employee($this->owner)->create();
    $stranger = User::factory()->create();

    foreach (['view', 'update', 'delete', 'restore', 'forceDelete'] as $ability) {
        expect($this->owner->can($ability, $this->file))->toBeTrue()
            ->and($teammate->can($ability, $this->file))->toBeTrue()
            ->and($stranger->can($ability, $this->file))->toBeFalse();
    }
});
