<?php

namespace Domain\Llm\Models;

use Database\Factories\LlmFactory;
use Domain\Llm\AbstractLlmProvider;
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
 * @property string $internal_name
 * @property string $external_name
 * @property string $descriptive_name
 * @property string|null $description
 * @property string|null $icon
 * @property int|null $context_window
 * @property array<string, bool> $features
 * @property string|null $location
 * @property array<string, mixed>|null $capabilities
 * @property bool $is_enabled
 * @property-read LlmProvider $provider
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @method static \Database\Factories\LlmFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Llm newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Llm newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Llm query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Llm whereContextWindow($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Llm whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Llm whereDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Llm whereDescriptiveName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Llm whereExternalName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Llm whereFeatures($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Llm whereIcon($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Llm whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Llm whereInternalName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Llm whereLocation($value)
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
 * with its names, context window and features. Its capabilities come from
 * the models.dev catalog ({@see ModelsDevCatalog}): whether it reasons, the
 * effort values it takes and whether it takes a temperature, so the app
 * sends each model what it takes instead of guessing from its name. For a
 * model the catalog does not list, the admin says whether it reasons. An admin adds
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
    private const array SAMPLING = ['temperature', 'top_p', 'presence_penalty', 'frequency_penalty'];

    protected function casts(): array
    {
        return [
            'features' => 'json',
            'capabilities' => 'json',
            'context_window' => 'integer',
            'is_enabled' => 'boolean',
        ];
    }

    #[\Override]
    protected $attributes = [
        'is_enabled' => true,
        'features' => '{}',
    ];

    /**
     * @return BelongsTo<LlmProvider, $this>
     */
    public function provider(): BelongsTo
    {
        return $this->belongsTo(LlmProvider::class, 'llm_provider_id');
    }

    /** Whether the model may be picked: it and its provider are on, and the provider has its key. */
    public function isAvailable(): bool
    {
        return $this->is_enabled && $this->provider->is_enabled && $this->provider->isConnected();
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
     * The settings to send this model: the sampling it takes, and the value
     * for the effort the app chose (Off by default: the least it takes).
     *
     * @param  array<string, mixed>  $settings
     * @return array<string, mixed>
     */
    public function requestSettings(array $settings, ReasoningEffort $effort = ReasoningEffort::Off): array
    {
        $settings = $this->sampling($settings);
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

    /** What the model's capabilities say, when any are known. */
    public function facts(): ?ModelFactsData
    {
        return is_array($this->capabilities) ? ModelFactsData::fromCatalog($this->capabilities) : null;
    }

    /** Whether the model thinks before it answers, so agents may set how hard. */
    public function reasons(): bool
    {
        return $this->facts()?->reasoning ?? false;
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
        return Cache::rememberForever('llms.catalog', fn () => self::query()->get()->load('provider')->sortBy('descriptive_name')->values());
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
