<?php

namespace App\Services\Infrastructure\LLM;

use Illuminate\Support\Facades\Http;

class OpenAI
{
    public static function transcribe(string $filePath): string
    {
        return Http::withHeaders([
            'Authorization' => 'Bearer '.config('services.openai.api_key'),
        ])
            ->retry(3, 3000)
            ->timeout(3600)
            ->attach('file', file_get_contents($filePath), basename($filePath))
            ->post('https://api.openai.com/v1/audio/transcriptions', [
                'model' => 'whisper-1',
            ])
            ->throw()
            ->json('text');
    }
}
