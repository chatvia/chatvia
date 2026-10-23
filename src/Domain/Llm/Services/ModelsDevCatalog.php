<?php

declare(strict_types=1);

namespace Domain\Llm\Services;

use Domain\Llm\Data\Catalog\ModelFactsData;
use Domain\Llm\Enums\LlmProviderKind;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * The models.dev catalog (open, community-kept): for each provider and model,
 * whether it reasons, the reasoning effort values it takes, whether it
 * takes a temperature, calls tools and keeps to a JSON schema, and the
 * longest reply it writes. The app reads these facts instead of guessing
 * from a model's name.
 *
 * The catalog (several MB) is fetched at most once a day, and only when it
 * changed (ETag); only the facts the app uses are kept, in the cache. It is
 * read when models are added and by the daily sync, never during a chat.
 * Switched off (`chatvia.ai.models_dev.enabled`), unreachable or not listing
 * a model, it gives no facts, and the admin's own switches decide.
 */
final class ModelsDevCatalog
{
    public const string URL = 'https://models.dev/catalog.json?type=all';

    private const string CACHE_KEY = 'llm:models-dev:facts';

    private const int MAX_AGE_HOURS = 24;

    private const int TIMEOUT_SECONDS = 30;

    /** The fields of a catalog entry the app reads; the rest is not kept. */
    private const array FIELDS = ['reasoning', 'reasoning_options', 'temperature', 'tool_call', 'structured_output', 'limit'];

    /**
     * The facts for a model as its provider names it. A server of your own
     * has no catalog entry of its own, so its model is looked up among the
     * catalog's general models by the same id.
     */
    public function facts(LlmProviderKind $kind, string $modelId): ?ModelFactsData
    {
        $entry = $this->entry($kind, $modelId);

        return $entry !== null ? ModelFactsData::fromCatalog($entry) : null;
    }

    /**
     * The model's catalog entry, cut to the fields the app reads
     * (`reasoning`, `reasoning_options`, `temperature`, `tool_call`,
     * `structured_output`, `limit`), for the model's row to keep. Null when
     * it is not listed or the lookup is off.
     *
     * @return array<string, mixed>|null
     */
    public function entry(LlmProviderKind $kind, string $modelId): ?array
    {
        return $this->enabled() ? $this->find($this->stored(refreshWhenOld: true), $kind, $modelId) : null;
    }

    /**
     * The model's entry as last downloaded, never downloading: for a model
     * that is not a catalog row, looked up while a request is answered.
     *
     * @return array<string, mixed>|null
     */
    public function storedEntry(LlmProviderKind $kind, string $modelId): ?array
    {
        return $this->enabled() ? $this->find($this->stored(refreshWhenOld: false), $kind, $modelId) : null;
    }

    /**
     * How many models the stored catalog lists for the providers the app
     * connects to, read again when a day old; 0 when it could not be read
     * or the lookup is off.
     */
    public function listedModels(): int
    {
        if (! $this->enabled()) {
            return 0;
        }

        $facts = $this->stored(refreshWhenOld: true);

        return array_sum(array_map(count(...), (array) ($facts['providers'] ?? [])));
    }

    /** Fetch the catalog now when it changed, whatever its age. */
    public function refresh(): void
    {
        if ($this->enabled()) {
            $this->fetch((array) Cache::get(self::CACHE_KEY, []));
        }
    }

    /**
     * @param  array<string, mixed>  $facts
     * @return array<string, mixed>|null
     */
    private function find(array $facts, LlmProviderKind $kind, string $modelId): ?array
    {
        $key = $kind->catalogKey();
        $entry = $key !== null ? ($facts['providers'][$key][$modelId] ?? null) : ($facts['models'][$modelId] ?? null);

        return is_array($entry) ? $entry : null;
    }

    private function enabled(): bool
    {
        return filter_var(config('chatvia.ai.models_dev.enabled', true), FILTER_VALIDATE_BOOL);
    }

    /**
     * @return array<string, mixed>
     */
    private function stored(bool $refreshWhenOld): array
    {
        $stored = (array) Cache::get(self::CACHE_KEY, []);
        $checkedAt = (int) ($stored['checked_at'] ?? 0);

        if ($refreshWhenOld && $checkedAt < now()->subHours(self::MAX_AGE_HOURS)->getTimestamp()) {
            return $this->fetch($stored);
        }

        return $stored;
    }

    /**
     * Download the catalog unless it is unchanged, and keep its facts. A
     * failed download keeps what is stored and is not tried again until the
     * next day, so a broken connection never slows each lookup. Facts kept
     * with other fields than the app now reads are downloaded again in full.
     *
     * @param  array<string, mixed>  $stored
     * @return array<string, mixed>
     */
    private function fetch(array $stored): array
    {
        $etag = filled($stored['etag'] ?? null) && ($stored['fields'] ?? null) === self::FIELDS ? $stored['etag'] : null;

        try {
            $response = Http::timeout(self::TIMEOUT_SECONDS)
                ->withHeaders(array_filter(['If-None-Match' => $etag]))
                ->get(self::URL);

            if ($response->status() === 304 && $etag !== null) {
                $fresh = [...$stored, 'checked_at' => now()->getTimestamp()];
            } else {
                $catalog = (array) $response->throw()->json();
                $fresh = [
                    'etag' => $response->header('ETag') ?: null,
                    'fields' => self::FIELDS,
                    'checked_at' => now()->getTimestamp(),
                    'providers' => $this->providerFacts((array) ($catalog['providers'] ?? [])),
                    'models' => $this->entries((array) ($catalog['models'] ?? [])),
                ];
            }
        } catch (Throwable $e) {
            Log::warning('Could not read the models.dev catalog; keeping the facts already stored.', ['error' => $e->getMessage()]);
            $fresh = [...$stored, 'checked_at' => now()->getTimestamp()];
        }

        Cache::forever(self::CACHE_KEY, $fresh);

        return $fresh;
    }

    /**
     * The entries of the providers the app connects to.
     *
     * @param  array<array-key, mixed>  $providers
     * @return array<string, array<string, array<string, mixed>>>
     */
    private function providerFacts(array $providers): array
    {
        $kept = [];

        foreach (LlmProviderKind::cases() as $kind) {
            $key = $kind->catalogKey();

            if ($key !== null && is_array($providers[$key]['models'] ?? null)) {
                $kept[$key] = $this->entries($providers[$key]['models']);
            }
        }

        return $kept;
    }

    /**
     * Each model's entry, cut to the fields the facts come from.
     *
     * @param  array<array-key, mixed>  $models
     * @return array<string, array<string, mixed>>
     */
    private function entries(array $models): array
    {
        $entries = [];

        foreach ($models as $id => $entry) {
            if (is_array($entry)) {
                $entries[(string) $id] = Arr::only($entry, self::FIELDS);
            }
        }

        return $entries;
    }
}
