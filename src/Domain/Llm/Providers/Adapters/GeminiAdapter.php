<?php

declare(strict_types=1);

namespace Domain\Llm\Providers\Adapters;

use Domain\Llm\Enums\LlmProviderKind;

/**
 * Google Gemini through its OpenAI-compatible endpoint. Its list names models
 * `models/gemini-…`; chat calls take the name without that prefix.
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

    /** The account's address is the native API the embeddings call; chat stays on its OpenAI-style address. */
    protected function sharesAccountAddress(): bool
    {
        return false;
    }

    protected function modelId(array $row): string
    {
        $id = (string) $row['id'];

        return str_starts_with($id, 'models/') ? substr($id, 7) : $id;
    }
}
