<?php

declare(strict_types=1);

namespace Domain\Llm\Providers\Adapters;

use Domain\Llm\Contracts\LlmProviderInterface;
use Domain\Llm\Data\Catalog\RemoteModelData;
use Domain\Llm\Enums\LlmProviderEnum;
use Domain\Llm\Models\LlmProvider;
use Domain\Llm\Providers\Mistral;

/**
 * Mistral's API. Its list says which models chat, read images and call
 * tools, and their context size. Its key is the Mistral account's
 * (Settings → AI providers), which also reads documents (OCR) and whole
 * PDFs.
 */
final class MistralAdapter extends OpenAiCompatibleAdapter
{
    public function kind(): LlmProviderEnum
    {
        return LlmProviderEnum::MISTRAL;
    }

    public function label(): string
    {
        return 'Mistral';
    }

    public function description(): string
    {
        return 'Mistral\'s models. The same key also reads documents and PDFs.';
    }

    public function defaultBaseUrl(): string
    {
        return 'https://api.mistral.ai/v1';
    }

    public function keysUrl(): string
    {
        return 'https://console.mistral.ai/api-keys';
    }

    public function readsDocuments(): bool
    {
        return true;
    }

    public function client(LlmProvider $provider): LlmProviderInterface
    {
        return new Mistral($provider, $this->baseUrl($provider), (string) $this->apiKey($provider));
    }

    protected function isChatModel(array $row): bool
    {
        return isset($row['capabilities']['completion_chat'])
            ? (bool) $row['capabilities']['completion_chat']
            : parent::isChatModel($row);
    }

    protected function toModel(array $row): RemoteModelData
    {
        $model = parent::toModel($row);
        $context = $row['max_context_length'] ?? null;

        return new RemoteModelData(
            id: $model->id,
            name: $model->name,
            contextWindow: is_numeric($context) ? (int) $context : $model->contextWindow,
            features: array_filter([
                'vision' => $row['capabilities']['vision'] ?? null,
                'function_calling' => $row['capabilities']['function_calling'] ?? null,
            ], is_bool(...)),
        );
    }
}
