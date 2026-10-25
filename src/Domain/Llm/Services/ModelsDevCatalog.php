<?php

declare(strict_types=1);

namespace Domain\Llm\Services;

use Domain\Llm\Data\Catalog\ModelFactsData;
use Domain\Llm\Enums\LlmProviderKind;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
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
 * It is always read: there is no switch. Unreachable or not listing a
 * model, it gives no facts, and the admin's own switches decide.
 *
 * It also has a logo for each lab (who made a model) and each provider: the
 * admin panel shows a model with its lab's logo, fetched once and kept.
 */
final class ModelsDevCatalog
{
    public const string URL = 'https://models.dev/catalog.json?type=all';

    public const string CACHE_KEY = 'llm:models-dev:facts';

    private const int MAX_AGE_HOURS = 24;

    private const int TIMEOUT_SECONDS = 30;

    private const string LOGO_URL = 'https://models.dev/logos/%s.svg';

    /** A logo: a lab's (`labs/alibaba`) or a provider's (`openrouter`). */
    public const string LOGO_PATTERN = '(labs/)?[a-z0-9][a-z0-9-]*';

    private const int LOGO_DAYS = 30;

    private const int LOGO_MAX_BYTES = 100_000;

    /** The fields of a catalog entry the app reads; the rest is not kept. */
    private const array FIELDS = ['name', 'reasoning', 'reasoning_options', 'temperature', 'tool_call', 'structured_output', 'limit', 'modalities', 'canonical_model_id'];

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
     * The model's catalog entry, cut to the fields the app reads (`name`,
     * `reasoning`, `reasoning_options`, `temperature`, `tool_call`,
     * `structured_output`, `limit`), for the model's row to keep. Null when
     * it is not listed.
     *
     * @return array<string, mixed>|null
     */
    public function entry(LlmProviderKind $kind, string $modelId): ?array
    {
        return $this->find($this->stored(refreshWhenOld: true), $kind, $modelId);
    }

    /**
     * The model's entry as last downloaded, never downloading: for a model
     * that is not a catalog row, looked up while a request is answered.
     *
     * @return array<string, mixed>|null
     */
    public function storedEntry(LlmProviderKind $kind, string $modelId): ?array
    {
        return $this->find($this->stored(refreshWhenOld: false), $kind, $modelId);
    }

    /**
     * The model's name for people (`DeepSeek V4.1 Flash`), as the catalog
     * last downloaded writes it; null when it is not listed. Never downloads:
     * chatters' model pickers read it while a page is served.
     */
    public function displayName(LlmProviderKind $kind, string $modelId): ?string
    {
        $name = $this->storedEntry($kind, $modelId)['name'] ?? null;

        return is_scalar($name) && filled((string) $name) ? (string) $name : null;
    }

    /**
     * How many models the stored catalog lists for the providers the app
     * connects to, read again when a day old; 0 when it could not be read
     */
    public function listedModels(): int
    {
        $facts = $this->stored(refreshWhenOld: true);

        return array_sum(array_map(count(...), (array) ($facts['providers'] ?? [])));
    }

    /**
     * The logo of a model as its provider names it: its lab's, read from the
     * catalog's own id for it (`alibaba/qwen3-235b-a22b` is Alibaba's), else
     * its provider's. A model the catalog does not list (a reranker or an
     * embedding model: it lists chat models only) takes the lab of a listed
     * model with the same prefix on that provider (`qwen/qwen3-reranker-8b`
     * is Alibaba's too). A model on a server of your own that the catalog
     * does not list has none. Never downloads the catalog.
     */
    public function logo(LlmProviderKind $kind, string $modelId): ?string
    {
        $facts = $this->stored(refreshWhenOld: false);
        $entry = $this->find($facts, $kind, $modelId);
        $canonical = $entry['canonical_model_id'] ?? ($kind->catalogKey() === null && $entry !== null ? $modelId : null);

        if (is_scalar($canonical) && Str::contains((string) $canonical, '/')) {
            return 'labs/'.Str::lower(Str::before((string) $canonical, '/'));
        }

        $lab = $entry === null && Str::contains($modelId, '/')
            ? $this->labsByPrefix($facts, $kind)[Str::before($modelId, '/')] ?? null
            : null;

        return $lab !== null ? "labs/{$lab}" : $kind->catalogKey();
    }

    /** The logo of a lab by its models.dev name (`cohere`), for a model on its own API that the catalog does not list. */
    public function labLogo(string $lab): string
    {
        return 'labs/'.Str::lower($lab);
    }

    /** The logo of a provider itself (`openrouter`); a server of your own has none. */
    public function providerLogo(LlmProviderKind $kind): ?string
    {
        return $kind->catalogKey();
    }

    /**
     * The SVG of a logo, downloaded once and kept for a month; null when it
     * could not be read (not tried again for a day).
     */
    public function logoSvg(string $logo): ?string
    {
        if (preg_match('#^'.self::LOGO_PATTERN.'$#', $logo) !== 1) {
            return null;
        }

        $key = "llm:models-dev:logo:{$logo}";
        $stored = Cache::get($key);

        // A logo that could not be read is kept as '', so it is not asked for again.
        if ($stored !== null) {
            return filled($stored) ? (string) $stored : null;
        }

        try {
            $body = Str::trim(Http::timeout(5)->get(sprintf(self::LOGO_URL, $logo))->throw()->body());
        } catch (Throwable) {
            $body = '';
        }

        $svg = Str::startsWith($body, ['<svg', '<?xml']) && strlen($body) <= self::LOGO_MAX_BYTES ? $body : '';

        Cache::put($key, $svg, $svg !== '' ? now()->addDays(self::LOGO_DAYS) : now()->addDay());

        return $svg !== '' ? $svg : null;
    }

    /** Fetch the catalog now when it changed, whatever its age. */
    public function refresh(): void
    {
        $this->fetch((array) Cache::memo()->get(self::CACHE_KEY, []));
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

    /**
     * The lab behind each model id prefix a provider uses (`qwen` => `alibaba`),
     * read from the models it lists.
     *
     * @param  array<string, mixed>  $facts
     * @return array<string, string>
     */
    private function labsByPrefix(array $facts, LlmProviderKind $kind): array
    {
        $key = $kind->catalogKey();

        return collect($key !== null ? (array) ($facts['providers'][$key] ?? []) : [])
            ->filter(fn (mixed $entry, string|int $id): bool => is_array($entry)
                && Str::contains((string) $id, '/')
                && Str::contains((string) ($entry['canonical_model_id'] ?? ''), '/'))
            ->mapWithKeys(fn (array $entry, string|int $id): array => [
                Str::before((string) $id, '/') => Str::lower(Str::before((string) $entry['canonical_model_id'], '/')),
            ])
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function stored(bool $refreshWhenOld): array
    {
        $stored = (array) Cache::memo()->get(self::CACHE_KEY, []);
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

        Cache::memo()->forever(self::CACHE_KEY, $fresh);

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
