<?php

namespace App\LLM\Operations;

use App\LLM\Contracts\LLMProviderInterface;
use Illuminate\Support\Facades\Http;

class Transcription
{
    public function __construct(
        public LLMProviderInterface $provider
    ) {}

    public function transcribe(string $filePath): string
    {
        $transcriptionEndpoint = 'https://api.scaleway.ai/ce82c941-d642-4fe0-94d7-b89bee83dd9d/v1/audio/transcriptions';

        $response = Http::withToken(config('services.scalewayai.api_key'))
            ->timeout(86000)
            ->attach(
                'file',
                file_get_contents($filePath),
                'audio.mp3'
            )
            ->post($transcriptionEndpoint, [
                'model' => 'whisper-large-v3',
            ])->throw()->json();

        return str($response['text'])->trim()->toString();
    }
}
