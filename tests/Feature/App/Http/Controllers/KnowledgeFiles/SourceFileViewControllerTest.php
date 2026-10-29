<?php

use App\Identity\Workspace\Models\User;
use Database\Factories\AgentFileFactory;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * GET external/files/{type}/{externalId} streams a stored knowledge file. The
 * workspace owner sees every file; a teammate only a file used by an agent
 * they manage and hold the training permission on. The controller answers
 * 404, never 403, so the endpoint does not confirm a file exists. Another
 * workspace's file is the isolation sweep's (CrossWorkspaceRouteTest).
 *
 * Uploads never run as a page of this site: only PDFs and raster images open
 * as themselves, text goes out as plain text, and every other answer is
 * sandboxed, also while the app-wide policy is enforced.
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

it('streams the file only to the owner and to a teammate who trains files on an agent using it', function (): void {
    $trainer = teammateWithPermissions($this->owner, ['agent.sources.train.files']);
    $withoutPermission = teammateWithPermissions($this->owner, allAgentPermissionsBut('agent.sources.train.files'));
    $outsideRole = teammateWithPermissions($this->owner, ['agent.sources.train.files'], allAgents: false, agentExternalIds: [createAgent($this->owner)->external_id]);

    expect($this->actingAs($this->owner)->get(sfvUrl($this->file))->assertOk()->streamedContent())->toBe('file body')
        ->and($this->actingAs($trainer)->get(sfvUrl($this->file))->assertOk()->streamedContent())->toBe('file body');

    $this->actingAs($withoutPermission)->get(sfvUrl($this->file))->assertNotFound();
    $this->actingAs($outsideRole)->get(sfvUrl($this->file))->assertNotFound();

    $this->file->agents()->detach();

    $this->actingAs($trainer)->get(sfvUrl($this->file))->assertNotFound();
});

it('serves a text file holding HTML as sandboxed plain text, also under an enforced app-wide policy', function (): void {
    Storage::put($this->file->filepath, '<!DOCTYPE html><html><body><script>alert(1)</script></body></html>');

    $response = $this->actingAs($this->owner)->get(sfvUrl($this->file))->assertOk();

    expect($response->headers->get('Content-Type'))->toBe('text/plain; charset=utf-8')
        ->and($response->headers->get('X-Content-Type-Options'))->toBe('nosniff')
        ->and($response->headers->get('Content-Security-Policy'))->toContain('sandbox')
        ->and($response->headers->get('Content-Security-Policy'))->toContain("default-src 'none'");

    config(['chatvia.security.csp' => 'enforce']);

    expect($this->get(sfvUrl($this->file))->assertOk()->headers->get('Content-Security-Policy'))->toContain('sandbox');
});

it('opens a PDF in the browser and downloads anything else as sandboxed raw bytes', function (string $body, string $type, string $disposition, bool $sandboxed): void {
    Storage::put($this->file->filepath, $body);

    $response = $this->actingAs($this->owner)->get(sfvUrl($this->file))->assertOk();

    expect($response->headers->get('Content-Type'))->toBe($type)
        ->and($response->headers->get('Content-Disposition'))->toStartWith($disposition)
        ->and(Str::contains((string) $response->headers->get('Content-Security-Policy'), 'sandbox'))->toBe($sandboxed);
})->with([
    'a PDF' => ["%PDF-1.4\n%\xE2\xE3\xCF\xD3\n1 0 obj\n<<>>\nendobj\ntrailer\n<<>>\n%%EOF\n", 'application/pdf', 'inline', false],
    'an SVG with a script' => ['<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>', 'application/octet-stream', 'attachment', true],
]);
