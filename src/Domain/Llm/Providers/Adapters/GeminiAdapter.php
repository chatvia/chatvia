<?php

declare(strict_types=1);

namespace Domain\Llm\Providers\Adapters;

use Domain\Llm\Enums\LlmProviderKind;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Str;

/**
 * Google Gemini through its OpenAI-compatible endpoint. Its list names models
 * `models/gemini-…`; chat calls take the name without that prefix. A wrong
 * key gets a 400 that says so, not a 401.
 */
final class GeminiAdapter extends OpenAiCompatibleAdapter
{
    protected const string NOT_CHAT = '/(embed|imagen|veo|aqa|tts|transcri)/i';

    public function kind(): LlmProviderKind
    {
        return LlmProviderKind::Google;
    }

    public function label(): string
    {
        return 'Google Gemini';
    }

    public function description(): string
    {
        return 'The Gemini models.';
    }

    public function defaultBaseUrl(): string
    {
        return 'https://generativelanguage.googleapis.com/v1beta/openai';
    }

    public function keysUrl(): string
    {
        return 'https://aistudio.google.com/apikey';
    }

    public function refusesKey(Response $response): bool
    {
        return parent::refusesKey($response)
            || ($response->status() === 400 && Str::contains(Str::lower((string) $response->json('error.message', $response->json('0.error.message'))), 'api key'));
    }

    /** The account's address is the native API the embeddings call; chat stays on its OpenAI-style address. */
    protected function sharesAccountAddress(): bool
    {
        return false;
    }

    protected function modelId(array $row): string
    {
        $id = (string) $row['id'];

        return Str::chopStart($id, 'models/');
    }
}
