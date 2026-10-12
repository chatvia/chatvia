<?php

declare(strict_types=1);

namespace Domain\Llm\Providers\Adapters;

use Domain\Llm\Enums\LlmProviderEnum;

/**
 * Google Gemini through its OpenAI-compatible endpoint. Its list names models
 * `models/gemini-…`; chat calls take the name without that prefix.
 */
final class GeminiAdapter extends OpenAiCompatibleAdapter
{
    protected const string NOT_CHAT = '/(embed|imagen|veo|aqa|tts|transcri)/i';

    public function kind(): LlmProviderEnum
    {
        return LlmProviderEnum::GOOGLE;
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

    protected function modelId(array $row): string
    {
        $id = (string) $row['id'];

        return str_starts_with($id, 'models/') ? substr($id, 7) : $id;
    }
}
