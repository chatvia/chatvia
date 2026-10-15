<?php

use Domain\Knowledge\Ingestion\Models\Chunk;
use Domain\Knowledge\Ingestion\Services\ChunkImageLink;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * GET my/chunks/{chunkExternalId}/{imageIndex}.png serves an image saved from
 * a document page. The route is public (visitors' browsers load the images an
 * answer links to), so only the link the server signed for that chunk and
 * that image is served; anything else is a 404, so a guessed chunk id cannot
 * read another workspace's pages.
 */
beforeEach(function (): void {
    Storage::fake();
    Storage::put('workspaces/acme/images/chart.png', 'chart bytes');
    Storage::put('workspaces/acme/images/table.png', 'table bytes');

    $this->chunk = Chunk::factory()->create(['metadata' => ['images' => [
        'workspaces/acme/images/chart.png' => 'A sales chart',
        'workspaces/acme/images/table.png' => 'A price table',
    ]]]);
});

/** The signature the server mints for one chunk image. */
function ciSignature(string $chunkExternalId, int $index): string
{
    return Str::after(ChunkImageLink::path($chunkExternalId, $index), '?'.ChunkImageLink::SIGNATURE_PARAM.'=');
}

it('serves the image a signed link names', function (): void {
    $response = $this->get(ChunkImageLink::path($this->chunk->external_id, 1))->assertOk();

    expect($response->streamedContent())->toBe('table bytes')
        ->and($response->headers->get('Content-Type'))->toBe('image/png');
});

it('answers 404 without a valid signature for that chunk and image', function (string $url): void {
    $this->get($url)->assertNotFound();
})->with([
    'no signature' => fn (): string => "/my/chunks/{$this->chunk->external_id}/1.png",
    'a made-up signature' => fn (): string => "/my/chunks/{$this->chunk->external_id}/1.png?s=".str_repeat('a', 64),
    'the signature of another image' => fn (): string => "/my/chunks/{$this->chunk->external_id}/1.png?s=".ciSignature($this->chunk->external_id, 0),
    'the signature of another chunk' => fn (): string => "/my/chunks/{$this->chunk->external_id}/1.png?s=".ciSignature(Chunk::factory()->create(['metadata' => $this->chunk->metadata])->external_id, 1),
]);

it('answers 404 for a signed index the chunk has no image for', function (): void {
    $this->get(ChunkImageLink::path($this->chunk->external_id, 2))->assertNotFound();
});
