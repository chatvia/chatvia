<?php

declare(strict_types=1);

namespace Domain\Knowledge\Embedding\Rerank;

use Domain\Knowledge\Embedding\Data\RelevanceThresholdsData;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * The reranking models the admin panel offers (Settings → AI models), strongest
 * first, each with a one-line note and its relevance thresholds. Thresholds
 * are measured with the evals in tests/Eval (README.md there), one model at a
 * time: a model's scores have their own scale. A model not listed here (a new
 * one on OpenRouter, a self-hosted one) uses the generic thresholds until it
 * is measured.
 *
 * OpenRouter's list is also read live (its public models list, cached for a
 * day), so a new reranker shows up without a Chatvia release.
 */
final class RerankModelCatalog
{
    /** Models on OpenRouter's `/rerank`, strongest first. */
    public const array OPENROUTER = [
        'cohere/rerank-4-pro' => ['label' => 'Cohere Rerank 4 Pro', 'note' => 'The strongest overall. 100+ languages.'],
        'voyageai/rerank-3' => ['label' => 'Voyage rerank-3', 'note' => 'Very strong, also on long documents.'],
        'qwen/qwen3-reranker-8b' => ['label' => 'Qwen3 Reranker 8B', 'note' => 'Open weights. Strong in many languages.'],
        'cohere/rerank-4-fast' => ['label' => 'Cohere Rerank 4 Fast', 'note' => 'Faster and cheaper than Pro.'],
        'voyageai/rerank-3-lite' => ['label' => 'Voyage rerank-3-lite', 'note' => 'Faster and cheaper than rerank-3.'],
    ];

    /** Models on Cohere's own API, strongest first. */
    public const array COHERE = [
        'rerank-v4.0-pro' => ['label' => 'Cohere Rerank 4 Pro', 'note' => 'The strongest overall. 100+ languages.'],
        'rerank-v4.0-fast' => ['label' => 'Cohere Rerank 4 Fast', 'note' => 'Faster and cheaper than Pro.'],
    ];

    /** The default model on OpenRouter. */
    public const string DEFAULT_OPENROUTER = 'cohere/rerank-4-pro';

    /** For models nobody has measured yet: drop only what is clearly unrelated. */
    private const array GENERIC = ['knowledge' => 0.30, 'tool_read' => 0.30, 'tool_write' => 0.40];

    /** Live list cache key and lifetime. */
    private const string LIVE_CACHE_KEY = 'rerank:openrouter-models';

    private const int LIVE_CACHE_SECONDS = 86400;

    public static function thresholds(string $model): RelevanceThresholdsData
    {
        $values = self::measured()[self::canonical($model)] ?? self::GENERIC;

        return new RelevanceThresholdsData($values['knowledge'], $values['tool_read'], $values['tool_write']);
    }

    /**
     * The OpenRouter rerankers to choose from: the curated ones first (with
     * their notes), then any other one OpenRouter lists today.
     *
     * @return array<string, string> model id => label
     */
    public function openRouterOptions(): array
    {
        $options = array_map(static fn (array $model): string => "{$model['label']} — {$model['note']}", self::OPENROUTER);

        foreach ($this->liveOpenRouterModels() as $id => $name) {
            $options[$id] ??= $name;
        }

        return $options;
    }

    /**
     * @return array<string, string> model id => label
     */
    public function cohereOptions(): array
    {
        return array_map(static fn (array $model): string => "{$model['label']} — {$model['note']}", self::COHERE);
    }

    /**
     * The rerankers OpenRouter lists now (public, no key needed); none when
     * it cannot be reached.
     *
     * @return array<string, string> model id => name
     */
    private function liveOpenRouterModels(): array
    {
        try {
            return Cache::remember(self::LIVE_CACHE_KEY, self::LIVE_CACHE_SECONDS, static function (): array {
                $models = Http::acceptJson()->timeout(5)
                    ->get('https://openrouter.ai/api/v1/models', ['output_modalities' => 'rerank'])
                    ->throw()
                    ->json('data');

                return collect(is_array($models) ? $models : [])
                    ->filter(static fn (mixed $model): bool => is_array($model) && is_string($model['id'] ?? null))
                    ->mapWithKeys(static fn (array $model): array => [$model['id'] => (string) ($model['name'] ?? $model['id'])])
                    ->all();
            });
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * The measured thresholds of each model, by the evals in tests/Eval. The
     * same model has the same scale on OpenRouter and on its maker's API.
     *
     * @return array<string, array{knowledge: float, tool_read: float, tool_write: float}>
     */
    private static function measured(): array
    {
        return [];
    }

    /** One name per model: Cohere's own ids and OpenRouter's ids for the same model meet. */
    private static function canonical(string $model): string
    {
        return match ($model) {
            'rerank-v4.0-pro' => 'cohere/rerank-4-pro',
            'rerank-v4.0-fast' => 'cohere/rerank-4-fast',
            default => $model,
        };
    }
}
