<?php

use App\Identity\Workspace\Models\User;
use Database\Factories\AgentFileFactory;
use Illuminate\Support\Facades\Storage;

/**
 * GET external/files/{type}/{externalId} streams a stored knowledge file. The
 * workspace owner sees every file; a teammate only a file used by an agent
 * they manage and hold the training permission on. The controller answers
 * 404, never 403, so the endpoint does not confirm a file exists. Another
 * workspace's file is the isolation sweep's (CrossWorkspaceRouteTest).
 *
 * Uploads never run as a page of this site: only PDFs and raster images open
 * as themselves, text goes out as plain text, and every answer is sandboxed.
 */
beforeEach(function (): void {
    Storage::fake();

    $this->owner = User::factory()->create();
    $this->agent = createAgent($this->owner);
    $this->file = AgentFileFactory::new()->create(['workspace_id' => $this->owner->workspace()->id, 'filename' => 'doc.txt']);
    $this->file->agents()->attach($this->agent->id);
    Storage::put($this->file->filepath, 'file body');
});

function sfvUrl(object $file, string $type = 'file'): string
{
    return route('external.source.files.view', ['type' => $type, 'externalId' => $file->external_id]);
}

it('streams the file to the workspace owner', function (): void {
    $response = $this->actingAs($this->owner)->get(sfvUrl($this->file))->assertOk();

    expect($response->streamedContent())->toBe('file body');
});

it('streams the file to a teammate who trains files on an agent using it', function (): void {
    $teammate = teammateWithPermissions($this->owner, ['agent.sources.train.files']);

    $response = $this->actingAs($teammate)->get(sfvUrl($this->file))->assertOk();

    expect($response->streamedContent())->toBe('file body');
});

it('returns 404 to a teammate without the file training permission', function (): void {
    $teammate = teammateWithPermissions($this->owner, allAgentPermissionsBut('agent.sources.train.files'));

    $this->actingAs($teammate)->get(sfvUrl($this->file))->assertNotFound();
});

it('returns 404 to a teammate whose role does not reach an agent using the file', function (): void {
    $other = createAgent($this->owner);
    $teammate = teammateWithPermissions($this->owner, ['agent.sources.train.files'], allAgents: false, agentExternalIds: [$other->external_id]);

    $this->actingAs($teammate)->get(sfvUrl($this->file))->assertNotFound();
});

it('returns 404 to a teammate for a file no agent uses', function (): void {
    $this->file->agents()->detach();
    $teammate = teammateWithPermissions($this->owner, ['agent.sources.train.files']);

    $this->actingAs($teammate)->get(sfvUrl($this->file))->assertNotFound();
});

it('signs out a deactivated account instead of streaming', function (): void {
    $this->owner->forceFill(['deactivated_at' => now()])->save();

    $this->actingAs($this->owner)->get(sfvUrl($this->file))->assertRedirect(route('login'));
});

it('returns 404 to a platform admin of another workspace', function (): void {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->get(sfvUrl($this->file))->assertNotFound();
});

it('returns 404 for an unknown type or malformed id', function (string $type, ?string $externalId): void {
    $this->actingAs($this->owner)
        ->get(route('external.source.files.view', ['type' => $type, 'externalId' => $externalId ?? $this->file->external_id]))
        ->assertNotFound();
})->with([
    'an unknown type' => ['nope', null],
    'a malformed id' => ['file', 'not-a-uuid'],
]);

it('serves a text file holding HTML as sandboxed plain text', function (): void {
    Storage::put($this->file->filepath, '<!DOCTYPE html><html><body><script>alert(1)</script></body></html>');

    $response = $this->actingAs($this->owner)->get(sfvUrl($this->file))->assertOk();

    expect($response->headers->get('Content-Type'))->toBe('text/plain; charset=utf-8')
        ->and($response->headers->get('X-Content-Type-Options'))->toBe('nosniff')
        ->and($response->headers->get('Content-Security-Policy'))->toContain('sandbox')
        ->and($response->headers->get('Content-Security-Policy'))->toContain("default-src 'none'");
});

it('keeps its sandbox when the app-wide policy is enforced', function (): void {
    config(['chatvia.security.csp' => 'enforce']);

    $response = $this->actingAs($this->owner)->get(sfvUrl($this->file))->assertOk();

    expect($response->headers->get('Content-Security-Policy'))->toContain('sandbox');
});

it('opens a PDF in the browser', function (): void {
    Storage::put($this->file->filepath, "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n1 0 obj\n<<>>\nendobj\ntrailer\n<<>>\n%%EOF\n");

    $response = $this->actingAs($this->owner)->get(sfvUrl($this->file))->assertOk();

    expect($response->headers->get('Content-Type'))->toBe('application/pdf')
        ->and($response->headers->get('Content-Disposition'))->toStartWith('inline')
        ->and($response->headers->get('Content-Security-Policy'))->not->toContain('sandbox');
});

it('downloads anything else as raw bytes', function (): void {
    Storage::put($this->file->filepath, '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>');

    $response = $this->actingAs($this->owner)->get(sfvUrl($this->file))->assertOk();

    expect($response->headers->get('Content-Type'))->toBe('application/octet-stream')
        ->and($response->headers->get('Content-Disposition'))->toStartWith('attachment')
        ->and($response->headers->get('Content-Security-Policy'))->toContain('sandbox');
});
