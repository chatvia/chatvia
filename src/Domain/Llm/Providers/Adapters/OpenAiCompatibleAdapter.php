<?php

declare(strict_types=1);

namespace Domain\Llm\Providers\Adapters;

use Domain\Llm\Contracts\LlmProviderInterface;
use Domain\Llm\Data\Catalog\RemoteModelData;
use Domain\Llm\Enums\AiProvider;
use Domain\Llm\Models\LlmProvider;
use Domain\Llm\Providers\Contracts\ProviderAdapter;
use Domain\Llm\Providers\OpenAiCompatible;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Collection;

/**
 * Base of the adapters for providers with an OpenAI-style API: chat through
 * `/chat/completions` and the model list from `GET /models` (`data[].id`).
 * A provider adapter overrides only what differs: its client, the fields its
 * list carries ({@see self::toModel()}), which models cannot chat
 * ({@see self::isChatModel()}), and what it knows about models its list says
 * little about ({@see self::knownModels()}).
 */
abstract class OpenAiCompatibleAdapter implements ProviderAdapter
{
    /**
     * Ids of models that are not chat models, for the lists that do not say.
     * Realtime models only speak over a live voice call (Settings → AI
     * models → Voice conversations), never in a chat reply.
     */
    protected const string NOT_CHAT = '/(embed|whisper|tts|dall-e|moderation|transcri|rerank|sora|imagen|image-gen|realtime)/i';

    public function defaultBaseUrl(): ?string
    {
        return null;
    }

    public function acceptsBaseUrl(): bool
    {
        return $this->defaultBaseUrl() === null;
    }

    public function baseUrlHelp(): ?string
    {
        return null;
    }

    public function baseUrl(LlmProvider $provider): string
    {
        return rtrim((string) ($provider->base_url ?: $this->accountBaseUrl() ?: $this->defaultBaseUrl()), '/');
    }

    public function needsApiKey(): bool
    {
        return true;
    }

    public function keysUrl(): ?string
    {
        return null;
    }

    public function readsDocuments(): bool
    {
        return false;
    }

    public function client(LlmProvider $provider): LlmProviderInterface
    {
        return new OpenAiCompatible($provider, $this->baseUrl($provider), (string) $this->apiKey($provider));
    }

    public function models(LlmProvider $provider): Collection
    {
        $response = $this->client($provider)
            ->client()
            ->timeout(20)
            ->get($this->baseUrl($provider).'/models')
            ->throw();

        $known = $this->knownModels();

        return collect((array) $response->json('data', []))
            ->filter(fn (mixed $row): bool => is_array($row) && is_string($row['id'] ?? null))
            ->map(fn (array $row): array => ['row' => $row, 'known' => $known[$this->modelId($row)] ?? null])
            ->filter(fn (array $model): bool => $model['known']['chat'] ?? $this->isChatModel($model['row']))
            ->map(fn (array $model): RemoteModelData => $this->withKnown($this->toModel($model['row']), $model['known']))
            ->unique('id')
            ->sortBy(fn (RemoteModelData $model): string => strtolower($model->name))
            ->values();
    }

    public function refusesKey(Response $response): bool
    {
        return in_array($response->status(), [401, 403], true);
    }

    public function account(): ?AiProvider
    {
        return AiProvider::forChatKind($this->kind());
    }

    public function apiKey(LlmProvider $provider): ?string
    {
        if (filled($provider->api_key)) {
            return $provider->api_key;
        }

        $account = $this->account();

        return $account instanceof AiProvider ? (config($account->apiKeyConfig()) ?: null) : null;
    }

    public function isConnected(LlmProvider $provider): bool
    {
        return ! $this->needsApiKey() || filled($this->apiKey($provider));
    }

    public function standalone(): LlmProvider
    {
        return new LlmProvider(['kind' => $this->kind(), 'name' => $this->label()]);
    }

    /**
     * The account's address when chat calls the same API as its other
     * services (a Scaleway project address, a Mistral proxy); null otherwise.
     */
    protected function accountBaseUrl(): ?string
    {
        $config = $this->sharesAccountAddress() ? $this->account()?->baseUrlConfig() : null;

        return $config === null ? null : (config($config) ?: null);
    }

    /** Whether chat calls the address set for the account. */
    protected function sharesAccountAddress(): bool
    {
        return true;
    }

    /**
     * The model's id as chat calls name it.
     *
     * @param  array<string, mixed>  $row
     */
    protected function modelId(array $row): string
    {
        return (string) $row['id'];
    }

    /**
     * Whether a listed model can chat, for a model the adapter does not know.
     *
     * @param  array<string, mixed>  $row
     */
    protected function isChatModel(array $row): bool
    {
        return preg_match(static::NOT_CHAT, $this->modelId($row)) !== 1;
    }

    /**
     * One listed model, with the fields an OpenAI-style list may carry: a
     * name, and the context size under one of its common names.
     *
     * @param  array<string, mixed>  $row
     */
    protected function toModel(array $row): RemoteModelData
    {
        $id = $this->modelId($row);
        $context = $row['context_length'] ?? $row['context_window'] ?? $row['max_model_len'] ?? null;

        return new RemoteModelData(
            id: $id,
            name: is_string($row['name'] ?? null) && filled($row['name']) ? $row['name'] : $id,
            contextWindow: is_numeric($context) ? (int) $context : null,
        );
    }

    /**
     * What the adapter knows about models whose list says little, by id:
     * `chat` (false for embedding or speech models), `context`, `vision`,
     * `function_calling`. Fills only what the list left empty.
     *
     * @return array<string, array{chat?: bool, context?: int, vision?: bool, function_calling?: bool}>
     */
    protected function knownModels(): array
    {
        return [];
    }

    /**
     * @param  array{chat?: bool, context?: int, vision?: bool, function_calling?: bool}|null  $known
     */
    private function withKnown(RemoteModelData $model, ?array $known): RemoteModelData
    {
        if ($known === null) {
            return $model;
        }

        return new RemoteModelData(
            id: $model->id,
            name: $model->name,
            contextWindow: $model->contextWindow ?? $known['context'] ?? null,
            features: $model->features + array_filter(
                ['vision' => $known['vision'] ?? null, 'function_calling' => $known['function_calling'] ?? null],
                is_bool(...),
            ),
        );
    }
}
