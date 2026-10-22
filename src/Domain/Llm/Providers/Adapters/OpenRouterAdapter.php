<?php

declare(strict_types=1);

namespace Domain\Llm\Providers\Adapters;

use Domain\Llm\Contracts\LlmProviderInterface;
use Domain\Llm\Data\Catalog\RemoteModelData;
use Domain\Llm\Enums\LlmProviderKind;
use Domain\Llm\Models\LlmProvider;
use Domain\Llm\Providers\OpenRouter;
use Illuminate\Support\Collection;

/**
 * OpenRouter: models from many companies with one key. Its list says the
 * most of any provider: names, context, what each model takes in and puts
 * out, and whether it calls tools. The list is public, so reading it first
 * asks about the key (`/key`), which refuses a wrong one.
 */
final class OpenRouterAdapter extends OpenAiCompatibleAdapter
{
    public function kind(): LlmProviderKind
    {
        return LlmProviderKind::OpenRouter;
    }

    public function label(): string
    {
        return 'OpenRouter';
    }

    public function description(): string
    {
        return 'Hundreds of models from many companies with one key. A good start.';
    }

    public function defaultBaseUrl(): string
    {
        return 'https://openrouter.ai/api/v1';
    }

    public function keysUrl(): string
    {
        return 'https://openrouter.ai/settings/keys';
    }

    public function client(LlmProvider $provider): LlmProviderInterface
    {
        return new OpenRouter($provider, $this->baseUrl($provider), $this->apiKey($provider) ?? '');
    }

    public function models(LlmProvider $provider): Collection
    {
        $this->client($provider)
            ->client()
            ->timeout(20)
            ->get($this->baseUrl($provider).'/key')
            ->throw();

        return parent::models($provider);
    }

    protected function isChatModel(array $row): bool
    {
        $outputs = $row['architecture']['output_modalities'] ?? null;

        return is_array($outputs) ? in_array('text', $outputs, true) : parent::isChatModel($row);
    }

    protected function toModel(array $row): RemoteModelData
    {
        $model = parent::toModel($row);
        $inputs = $row['architecture']['input_modalities'] ?? null;
        $parameters = $row['supported_parameters'] ?? null;

        return new RemoteModelData(
            id: $model->id,
            name: $model->name,
            features: array_filter([
                'vision' => is_array($inputs) ? in_array('image', $inputs, true) : null,
                'function_calling' => is_array($parameters) ? in_array('tools', $parameters, true) : null,
                'reasoning' => is_array($parameters) ? in_array('reasoning', $parameters, true) : null,
            ], is_bool(...)),
        );
    }
}
