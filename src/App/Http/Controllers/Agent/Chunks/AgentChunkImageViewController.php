<?php

namespace App\Http\Controllers\Agent\Chunks;

use Domain\Knowledge\Ingestion\Models\Chunk;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Support\Http\ChunkImageLink;

/**
 * Serves an image saved from a document page, by chunk and image position.
 * Public (visitors' browsers load the images the agent links to), so every
 * URL must carry the server-minted signature.
 */
class AgentChunkImageViewController
{
    public function __invoke(Request $request, string $chunkExternalId, int $imageIndex)
    {
        // This route is public (anonymous visitor browsers load the agent's
        // markdown images), so it cannot sit behind `auth`. Instead every
        // legitimate URL carries a constant-time HMAC the server minted in
        // ChatService; reject anything else so a guessed chunk id can't be used
        // to read another tenant's ingested page images (cross-tenant IDOR).
        abort_unless(
            ChunkImageLink::verify($chunkExternalId, $imageIndex, $request->query(ChunkImageLink::SIGNATURE_PARAM)),
            404,
        );

        $metadata = Chunk::query()->where('external_id', $chunkExternalId)
            ->select('metadata')
            ->value('metadata');

        if (! $metadata || ! isset($metadata['images'])) {
            abort(404);
        }

        $images = array_combine(
            range(0, (count($metadata['images']) - 1)),
            array_keys($metadata['images'])
        );

        if (! isset($images[$imageIndex])) {
            abort(404);
        }

        $imagePath = $images[$imageIndex];

        if (! Storage::exists($imagePath)) {
            abort(404);
        }

        return Storage::response($imagePath, null, [
            'Content-Type' => 'image/png',
            'Cache-Control' => 'public, max-age=31536000, immutable',
        ]);
    }
}
