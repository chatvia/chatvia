<?php

namespace App\Http\Controllers\Webhooks;

use App\Security\HMAC;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class WebhookChunkController
{
    public function __invoke(Request $request, HMAC $hmac): void
    {
        $authHash = $request->header('X-Auth-Hash');
        $chunkId = $request->string('chunk_id');

        Log::info('Chunk ID: '.$chunkId.'. Hash: '.$authHash);

        abort_unless($authHash !== null, 401);

        $passed = $this->verify($chunkId, $authHash);

        logger('Passed? '.(int) $passed);
    }

    private function verify(string $payload, string $signature): bool
    {
        $expectedSignature = hash_hmac('sha256', $payload, config('services.chunker.secret'));

        return hash_equals($expectedSignature, $signature);
    }
}
