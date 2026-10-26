<?php

declare(strict_types=1);

namespace Domain\Llm\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * The image models OpenRouter offers, for the picker in Settings → AI
 * models → Image generation: a few strong ones first, each with a note, then
 * every other model whose output includes images that OpenRouter lists today
 * (its public list, cached for a day). The list also says which models write
 * text next to the picture, since those must be asked for both.
 */
final class OpenRouterImageModels
{
    /** Strong models, the default first. */
    public const array CURATED = [
        'google/gemini-nano-banana-2.1' => 'Gemini Nano Banana 2.1: good pictures, fast. The default.',
        'openai/gpt-image-2.5-flare' => 'GPT Image 2.5 Flare: fast, follows the prompt closely.',
        'google/gemini-3.1-flash-lite-image' => 'Gemini 3.1 Flash Lite Image: the cheapest.',
        'google/gemini-3-pro-image' => 'Gemini 3 Pro Image: the best Gemini pictures, costs more.',
    ];

    /** Curated models that write text next to the picture. */
    private const array WRITE_TEXT = ['google/gemini-nano-banana-2.1', 'google/gemini-3.1-flash-lite-image', 'google/gemini-3-pro-image'];

    private const string CACHE_KEY = 'image:openrouter-models';

    private const int CACHE_SECONDS = 86400;

    /**
     * The models to choose from: the curated ones (with their notes), then
     * the others OpenRouter lists now.
     *
     * @return array<string, string> model id => label
     */
    public function options(): array
    {
        $options = self::CURATED;

        foreach ($this->live() as $id => $model) {
            $options[$id] ??= $model['name'];
        }

        return $options;
    }

    /**
     * Whether a model writes text as well as pictures. A model OpenRouter does
     * not list now is taken to write both, as most image models there do.
     */
    public function writesText(string $model): bool
    {
        $listed = $this->live()[$model] ?? null;

        if ($listed === null) {
            return in_array($model, self::WRITE_TEXT, true) || ! array_key_exists($model, self::CURATED);
        }

        return $listed['text'];
    }

    /**
     * The models OpenRouter lists now whose output includes images (public,
     * no key needed). None when it cannot be reached.
     *
     * @return array<string, array{name: string, text: bool}> by model id
     */
    private function live(): array
    {
        try {
            return Cache::remember(self::CACHE_KEY, self::CACHE_SECONDS, static function (): array {
                $models = Http::acceptJson()->timeout(5)
                    ->get('https://openrouter.ai/api/v1/models', ['output_modalities' => 'image'])
                    ->throw()
                    ->json('data');

                return collect(is_array($models) ? $models : [])
                    ->filter(static fn (mixed $model): bool => is_array($model) && filled($model['id'] ?? null))
                    ->mapWithKeys(static fn (array $model): array => [$model['id'] => [
                        'name' => (string) ($model['name'] ?? $model['id']),
                        'text' => in_array('text', (array) data_get($model, 'architecture.output_modalities', []), true),
                    ]])
                    ->all();
            });
        } catch (Throwable) {
            return [];
        }
    }
}
