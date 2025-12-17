<?php

namespace App\Services\Infrastructure\LLM\Operations;

use App\Contracts\LLMProviderInterface;
use App\Services\Infrastructure\LLM\Scaleway;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class Transcription
{
    public function __construct(
        public LLMProviderInterface $provider
    ) {}

    public function transcribe(string $filePath, ?string $prompt = 'You are a helpful assisatant', ?string $language = 'en'): string
    {
        $transcriptionEndpoint = 'https://api.scaleway.ai/ce82c941-d642-4fe0-94d7-b89bee83dd9d/v1/audio/transcriptions';

        $response = Http::withToken(config('services.scalewayai.api_key'))
            ->attach(
                'file',
                file_get_contents($filePath),
                'audio.mp3'
            )
            ->post($transcriptionEndpoint, [
                'model' => 'whisper-large-v3',
//                'prompt' => 'You are a helpful assistant',
//                'language' => 'en',
            ])->json();

        return str($response['text'])->trim()->toString();
    }
}
