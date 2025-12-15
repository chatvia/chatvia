<?php

namespace App\Services\Infrastructure\LLM\Operations;

use App\Contracts\LLMProviderInterface;
use App\Services\Infrastructure\LLM\Scaleway;
use RuntimeException;

class Transcription
{
    public function __construct(
        public LLMProviderInterface $provider
    ) {}

    public function transcribe(string $filePath, ?string $prompt = null, ?string $language = null): string
    {
        // Only Scaleway provider supports transcription for now
        if (! $this->provider instanceof Scaleway) {
            throw new RuntimeException('Transcription is only supported with Scaleway provider');
        }

        $transcriptionEndpoint = 'https://api.scaleway.ai/ce82c941-d642-4fe0-94d7-b89bee83dd9d/v1/audio/transcriptions';

        // Build form data parameters
        $formData = [
            'model' => 'whisper-large-v3',
        ];

        // Add optional parameters if provided
        if ($prompt) {
            $formData['prompt'] = $prompt;
        }

        if ($language) {
            $formData['language'] = $language;
        }

        return $this->provider->client()
            ->timeout(3600)
            ->attach('file', file_get_contents($filePath), basename($filePath))
            ->post($transcriptionEndpoint, $formData)
            ->throw()
            ->json('text');
    }
}
