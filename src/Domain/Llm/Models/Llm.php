<?php

namespace Domain\Llm\Models;

use Database\Factories\LlmFactory;
use Domain\Llm\AbstractLlmProvider;
use Domain\Llm\Actions\Catalog\CheckLlmListingAction;
use Domain\Llm\Data\Catalog\ModelFactsData;
use Domain\Llm\Enums\ReasoningEffort;
use Domain\Llm\Services\ModelsDevCatalog;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * @property int $id
 * @property int $llm_provider_id
 * @property string $name
 * @property array<string, mixed>|null $capabilities
 * @property bool $is_enabled
 * @property bool $is_reasoning_enabled
 * @property Carbon|null $unlisted_at
 * @property-read LlmProvider $provider
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @method static \Database\Factories\LlmFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Llm newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Llm newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Llm query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Llm whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Llm whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Llm whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Llm whereLlmProviderId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Llm whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
#[Guarded([
    'id',
])]
/**
 * A model in the LLM catalog: one model on one provider ({@see LlmProvider}),
 * named as the provider names it (`name`, sent in every call); the app
 * refers to it by its row id. Its capabilities come from
 * the models.dev catalog ({@see ModelsDevCatalog}): whether it reasons, the
 * effort values it takes, whether it takes a temperature, calls tools and
 * keeps to a JSON schema, and its longest reply, so the app sends each model
 * only what it takes instead of guessing from its name. For a model the catalog does not list, the admin says
 * whether it reasons, and the other settings go as asked. The admin may also
 * turn reasoning off for a model that can reason (`is_reasoning_enabled`). An admin adds
 * the models an install may use; agents and the app's own tasks pick theirs
 * from it, and every call to a model goes to its provider.
 */
class Llm extends Model
{
    /** @use HasFactory<LlmFactory> */
    use HasFactory;

    /** Effort values from the least thinking to the most, as the catalog names them. */
    private const array EFFORT_ORDER = ['none', 'minimal', 'low', 'medium', 'high', 'xhigh', 'max'];

    /** The sampling settings a model that takes no temperature refuses. */
    private const array SAMPLING = ['temperature', 'top_p', 'top_k', 'presence_penalty', 'frequency_penalty'];

    /** The request keys that cap the reply length, as the providers name it. */
    private const array REPLY_LENGTH = ['max_tokens', 'max_completion_tokens'];

    /** The request keys that offer tools, which a model that calls none refuses. */
    private const array TOOLS = ['tools', 'tool_choice', 'parallel_tool_calls'];

    protected function casts(): array
    {
        return [
            'capabilities' => 'json',
            'is_enabled' => 'boolean',
            'is_reasoning_enabled' => 'boolean',
            'unlisted_at' => 'datetime',
        ];
    }

    #[\Override]
    protected $attributes = [
        'is_enabled' => true,
        'is_reasoning_enabled' => true,
    ];

    /**
     * @return BelongsTo<LlmProvider, $this>
     */
    public function provider(): BelongsTo
    {
        return $this->belongsTo(LlmProvider::class, 'llm_provider_id');
    }

    /**
     * Whether the model may be picked: it and its provider are on, the
     * provider has its key, and it still serves the model (see
     * {@see CheckLlmListingAction}).
     */
    public function isAvailable(): bool
    {
        return $this->is_enabled && $this->unlisted_at === null && $this->provider->is_enabled && $this->provider->isConnected();
    }

    /**
     * The effort value to send for an effort the app chose, from the values
     * the model takes: Off is the least of them; Low, Medium and High go as
     * named when listed, else as the nearest listed value that still thinks
     * (the higher one on a tie). When the values are not known, Off sends
     * nothing (the provider's default) and the others go as named.
     */
    public function effortValue(ReasoningEffort $effort): ?string
    {
        $values = $this->knownEfforts();

        if ($values === []) {
            return $effort->isOff() ? null : $effort->value;
        }

        if ($effort->isOff()) {
            return $values[0];
        }

        if (in_array($effort->value, $values, true)) {
            return $effort->value;
        }

        $thinking = array_values(array_diff($values, ['none']));
        $wanted = (int) array_search($effort->value, self::EFFORT_ORDER, true);

        return collect($thinking === [] ? $values : $thinking)
            ->sortBy(static fn (string $value): array => [
                abs((int) array_search($value, self::EFFORT_ORDER, true) - $wanted),
                -(int) array_search($value, self::EFFORT_ORDER, true),
            ])
            ->first();
    }

    /**
     * The settings to send this model: the sampling it takes, a reply length
     * it can write, tools only when it calls them, and the value for the
     * effort the app chose (Off by default: the least it takes).
     *
     * @param  array<string, mixed>  $settings
     * @return array<string, mixed>
     */
    public function requestSettings(array $settings, ReasoningEffort $effort = ReasoningEffort::Off): array
    {
        $settings = $this->replyLength($this->sampling($settings));

        if (! $this->callsTools()) {
            $settings = Arr::except($settings, self::TOOLS);
        }

        $value = $this->effortValue($effort);

        return $value === null ? $settings : [...$settings, AbstractLlmProvider::REASONING_EFFORT => $value];
    }

    /**
     * The request settings the model takes: a model that takes no
     * temperature also refuses the other sampling settings.
     *
     * @param  array<string, mixed>  $settings
     * @return array<string, mixed>
     */
    public function sampling(array $settings): array
    {
        return $this->facts()?->takesTemperature === false ? Arr::except($settings, self::SAMPLING) : $settings;
    }

    /**
     * The settings with a reply length no longer than the longest the model
     * writes: a provider refuses a request that asks for more.
     *
     * @param  array<string, mixed>  $settings
     * @return array<string, mixed>
     */
    public function replyLength(array $settings): array
    {
        $limit = $this->facts()?->maxOutputTokens;

        if ($limit === null) {
            return $settings;
        }

        foreach (self::REPLY_LENGTH as $key) {
            if (is_int($settings[$key] ?? null)) {
                $settings[$key] = min($settings[$key], $limit);
            }
        }

        return $settings;
    }

    /** What the model's capabilities say, when any are known. */
    public function facts(): ?ModelFactsData
    {
        return is_array($this->capabilities) ? ModelFactsData::fromCatalog($this->capabilities) : null;
    }

    /** Whether the model can call tools: yes unless its facts say it cannot. */
    public function callsTools(): bool
    {
        return $this->facts()?->callsTools !== false;
    }

    /** Whether the model reads images, as its facts say; null when they do not say. */
    public function readsImages(): ?bool
    {
        return $this->facts()?->readsImages;
    }

    /** Whether the model reads PDF files, as its facts say; null when they do not say. */
    public function readsFiles(): ?bool
    {
        return $this->facts()?->readsFiles;
    }

    /**
     * Whether the provider can hold the model's reply to a JSON schema: yes
     * unless its facts say it cannot.
     */
    public function takesResponseSchema(): bool
    {
        return $this->facts()?->takesResponseSchema !== false;
    }

    /** Whether the model can think before it answers, as its facts say. */
    public function canReason(): bool
    {
        return $this->facts()?->reasoning ?? false;
    }

    /**
     * Whether the model thinks before it answers, so agents may set how hard:
     * it can, and the admin has not turned reasoning off for it.
     */
    public function reasons(): bool
    {
        return $this->is_reasoning_enabled && $this->canReason();
    }

    /**
     * The listed effort values the app knows, least thinking first.
     *
     * @return list<string>
     */
    private function knownEfforts(): array
    {
        return array_values(array_intersect(self::EFFORT_ORDER, (array) $this->facts()?->efforts));
    }

    /**
     * Cache the entire LLM catalog. A tiny static lookup table that's read on
     * almost every chat/widget request — refreshes only when an LLM is added,
     * updated, or removed via the events below.
     *
     * Uses `self::query()` (not `static`) so the generic resolves to
     * `Collection<int, Llm>` matching the declared return — `static::query()`
     * would type as `Collection<int, static>`, and Eloquent\Collection's
     * TModel is non-covariant so the two aren't interchangeable.
     *
     * @return Collection<int, Llm>
     */
    public static function catalog(): Collection
    {
        return Cache::rememberForever('llms.catalog', fn () => self::query()->get()->load('provider')->sortBy('name')->values());
    }

    /**
     * The catalog model a setting names (it holds the model's id, as text),
     * or null when it names none.
     */
    public static function fromSetting(mixed $value): ?self
    {
        $id = (int) $value;

        return $id > 0 ? self::catalog()->firstWhere('id', $id) : null;
    }

    /**
     * The catalog models that may be picked, for agents and the app's own
     * tasks.
     *
     * @return Collection<int, Llm>
     */
    public static function available(): Collection
    {
        return self::catalog()->filter(fn (Llm $llm): bool => $llm->isAvailable())->values();
    }

    public static function flushCatalogCache(): void
    {
        Cache::forget('llms.catalog');
    }

    protected static function booted(): void
    {
        static::saved(fn () => static::flushCatalogCache());
        static::deleted(fn () => static::flushCatalogCache());
    }
}
