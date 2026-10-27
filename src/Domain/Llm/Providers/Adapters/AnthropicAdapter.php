<?php

declare(strict_types=1);

namespace Domain\Llm\Providers\Adapters;

use Domain\Llm\Contracts\ChatCompletionProvider;
use Domain\Llm\Data\Catalog\RemoteModelData;
use Domain\Llm\Enums\LlmProviderKind;
use Domain\Llm\Models\LlmProvider;
use Domain\Llm\Providers\Anthropic;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Anthropic's API: the Claude models, through its own Messages API
 * ({@see Anthropic}). Its model list pages and names models by
 * `display_name`; every Claude model it lists chats, reads images and calls
 * tools. Its key is the Anthropic account's (Settings → AI
 * providers).
 */
final class AnthropicAdapter extends OpenAiCompatibleAdapter
{
    /** More models than Anthropic has ever listed, so the list fits one page. */
    private const int MODEL_PAGE_SIZE = 1000;

    public function kind(): LlmProviderKind
    {
        return LlmProviderKind::Anthropic;
    }

    public function label(): string
    {
        return 'Anthropic';
    }

    public function description(): string
    {
        return 'The Claude models.';
    }

    public function defaultBaseUrl(): string
    {
        return 'https://api.anthropic.com/v1';
    }

    public function keysUrl(): string
    {
        return 'https://console.anthropic.com/settings/keys';
    }

    public function client(LlmProvider $provider): ChatCompletionProvider
    {
        return new Anthropic($provider, $this->baseUrl($provider), $this->apiKey($provider) ?? '');
    }

    public function models(LlmProvider $provider): Collection
    {
        $response = $this->client($provider)
            ->client()
            ->timeout(20)
            ->get($this->baseUrl($provider).'/models', ['limit' => self::MODEL_PAGE_SIZE])
            ->throw();

        return collect((array) $response->json('data', []))
            ->filter(fn (mixed $row): bool => is_array($row) && filled($row['id'] ?? null))
            ->map(fn (array $row): RemoteModelData => $this->toModel($row))
            ->unique('id')
            ->sortBy(fn (RemoteModelData $model): string => Str::lower($model->name))
            ->values();
    }

    protected function toModel(array $row): RemoteModelData
    {
        $id = $this->modelId($row);

        return new RemoteModelData(
            id: $id,
            name: filled($row['display_name'] ?? null) ? $row['display_name'] : $id,
            features: ['vision' => true, 'function_calling' => true],
        );
    }
}
