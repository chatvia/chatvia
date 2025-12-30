<?php

namespace App\Http\Controllers;

use App\Services\Security\HMAC;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class WebhookChunkController
{
    public function __invoke(Request $request, HMAC $hmac)
    {
        $authHash = $request->header('X-Auth-Hash');
        $chunkId = $request->string('chunk_id');

        Log::info('Chunk ID: '.$chunkId.'. Hash: '.$authHash);

        abort_unless($authHash, 401);

        $passed = $this->verify($chunkId, $authHash);

        logger('Passed? '.(int) $passed);
    }

    public function verify(string $payload, string $signature): bool
    {
        $expectedSignature = hash_hmac('sha256', $payload, config('services.chunker.secret'));

        return hash_equals($expectedSignature, $signature);
    }
}
