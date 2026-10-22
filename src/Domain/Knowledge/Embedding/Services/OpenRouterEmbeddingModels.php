<?php

declare(strict_types=1);

namespace Domain\Knowledge\Embedding\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

/**
 * The embedding models OpenRouter offers, for the pickers in the setup and
 * Settings → AI models: a few strong ones first, each with a note and the
 * vector sizes it can return, then every other one OpenRouter lists today
 * (its public list, cached for a day). The size of a listed model is not
 * known here: the test embedding made when it is chosen checks it.
 */
final class OpenRouterEmbeddingModels
{
    /** Strong models, best value first, with the smallest and largest size each returns. */
    public const array CURATED = [
        'openai/text-embedding-3-small' => ['label' => 'OpenAI text-embedding-3-small', 'note' => 'Good and cheap.', 'min' => 1, 'max' => 1536],
        'openai/text-embedding-3-large' => ['label' => 'OpenAI text-embedding-3-large', 'note' => 'Stronger, at about 6 times the price.', 'min' => 1, 'max' => 3072],
        'qwen/qwen3-embedding-8b' => ['label' => 'Qwen3 Embedding 8B', 'note' => 'Open weights. Strong in many languages.', 'min' => 32, 'max' => 4096],
        'google/gemini-embedding-001' => ['label' => 'Gemini Embedding', 'note' => 'Strong in many languages.', 'min' => 128, 'max' => 3072],
        'mistralai/mistral-embed-2312' => ['label' => 'Mistral Embed', 'note' => 'Returns 1024 dimensions only.', 'min' => 1024, 'max' => 1024],
    ];

    public const string DEFAULT = 'openai/text-embedding-3-small';

    private const string CACHE_KEY = 'embedding:openrouter-models';

    private const int CACHE_SECONDS = 86400;

    /**
     * Whether a model can return vectors of this size. True for a model not
     * curated here: OpenRouter asks it for the size, and the test embedding
     * catches one that cannot.
     */
    public static function fits(string $model, int $dimensions): bool
    {
        $known = self::CURATED[$model] ?? null;

        return $known === null || ($dimensions >= $known['min'] && $dimensions <= $known['max']);
    }

    /**
     * The models to choose from: the curated ones that fit the stored size
     * (with their notes), then the others OpenRouter lists now.
     *
     * @return array<string, string> model id => label
     */
    public function options(int $dimensions): array
    {
        $options = [];

        foreach (self::CURATED as $id => $model) {
            if (self::fits($id, $dimensions)) {
                $options[$id] = "{$model['label']} — {$model['note']}".($id === self::DEFAULT ? ' The default.' : '');
            }
        }

        foreach ($this->live() as $id => $name) {
            $options[$id] ??= $name;
        }

        return $options;
    }

    /**
     * The embedding models OpenRouter lists now (public, no key needed),
     * without the `:batch` copies, which answer later and not in the
     * request. None when it cannot be reached.
     *
     * @return array<string, string> model id => name
     */
    private function live(): array
    {
        try {
            return Cache::remember(self::CACHE_KEY, self::CACHE_SECONDS, static function (): array {
                $models = Http::acceptJson()->timeout(5)
                    ->get('https://openrouter.ai/api/v1/embeddings/models')
                    ->throw()
                    ->json('data');

                return collect(is_array($models) ? $models : [])
                    ->filter(static fn (mixed $model): bool => is_array($model) && filled($model['id'] ?? null) && ! Str::endsWith($model['id'], ':batch'))
                    ->mapWithKeys(static fn (array $model): array => [$model['id'] => (string) ($model['name'] ?? $model['id'])])
                    ->all();
            });
        } catch (Throwable) {
            return [];
        }
    }
}
