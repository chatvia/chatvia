<?php

namespace App\Http\Controllers\Webhooks;

use Illuminate\Http\Request;
use Support\Http\WebhookSignature;

class WebhookChunkController
{
    public function __invoke(Request $request): void
    {
        // Unauthenticated + CSRF-exempt callback from the chunker pipeline.
        // Enforce the HMAC over the chunk id and reject on mismatch. (The auth
        // hash is never logged.)
        $chunkId = $request->string('chunk_id')->toString();

        abort_unless(
            WebhookSignature::verify($chunkId, $request->header('X-Auth-Hash'), (string) config('services.chunker.hash_key')),
            401,
        );
    }
}
