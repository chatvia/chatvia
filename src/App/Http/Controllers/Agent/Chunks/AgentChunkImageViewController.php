<?php

namespace App\Http\Controllers\Agent\Chunks;

use Domain\Ingestion\Models\Chunk;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class AgentChunkImageViewController
{
    public function __invoke(Request $request, string $chunkExternalId, int $imageIndex)
    {
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
