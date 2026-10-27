<?php

declare(strict_types=1);

namespace Domain\Llm\Providers;

use Domain\Llm\AbstractLlmProvider;
use Domain\Llm\Enums\LlmProviderKind;
use Domain\Llm\Providers\Concerns\StreamsThroughResponses;
use Domain\Llm\Services\ModelsDevCatalog;
use Illuminate\Support\Str;
use Psr\Http\Message\ResponseInterface;

/**
 * Chat completions through Azure OpenAI's v1 API, which is OpenAI's API in
 * the admin's own Azure resource: the key goes in the `api-key` header, and
 * the model is the name of a deployment.
 *
 * A resource serves OpenAI's models and others (DeepSeek, Llama, Grok...).
 * Chat turns of OpenAI's models stream through the Responses API, like
 * {@see OpenAi}, for their thinking summaries; the others, and deployments
 * the catalog does not know by name, stream through Chat Completions, which
 * every model there takes.
 */
class Azure extends AbstractLlmProvider
{
    use StreamsThroughResponses {
        stream as private streamThroughResponses;
    }

    /**
     * @param  list<array<string, mixed>>  $messages
     * @param  array<string, mixed>  $settings
     */
    public function stream(string $model, array $messages, array $settings = []): ResponseInterface
    {
        return $this->isOpenAiModel($model)
            ? $this->streamThroughResponses($model, $messages, $settings)
            : parent::stream($model, $messages, $settings);
    }

    /**
     * @return array<string, string>
     */
    public function headers(): array
    {
        return array_merge(
            array_filter(['api-key' => $this->getApiKey() !== '' ? $this->getApiKey() : null]),
            ['Content-Type' => 'application/json'],
            $this->connection->headers ?? [],
        );
    }

    /**
     * No model here takes `top_k`, which the agent's stored llmSettings
     * always include. OpenAI's newer models refuse `max_tokens`, and every
     * OpenAI model takes it as `max_completion_tokens`.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    protected function preparePayload(array $payload): array
    {
        unset($payload['top_k']);

        if (array_key_exists('max_tokens', $payload) && $this->isOpenAiModel((string) ($payload['model'] ?? ''))) {
            $payload['max_completion_tokens'] ??= $payload['max_tokens'];
            unset($payload['max_tokens']);
        }

        return $payload;
    }

    /** Whether the deployment serves one of OpenAI's models, by the catalog's own id for it. */
    private function isOpenAiModel(string $model): bool
    {
        $entry = app(ModelsDevCatalog::class)->storedEntry(LlmProviderKind::Azure, $model);

        return Str::startsWith((string) ($entry['canonical_model_id'] ?? ''), 'openai/');
    }
}
