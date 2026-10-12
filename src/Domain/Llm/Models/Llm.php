<?php

namespace Domain\Llm\Models;

use Database\Factories\LlmFactory;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
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
 * @property bool $is_reasoning
 * @property numeric|null $input_price_per_1m_tokens
 * @property numeric|null $output_price_per_1m_tokens
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
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Llm whereInputPricePer1mTokens($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Llm whereInternalName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Llm whereLocation($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Llm whereOutputPricePer1mTokens($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Llm whereLlmProviderId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Llm whereIsReasoning($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Llm whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
#[Guarded([
    'id',
])]
/**
 * A model in the LLM catalog: one model on one provider ({@see LlmProvider}),
 * with its names, context window, features and token prices. An admin adds
 * the models an install may use; agents and the app's own tasks pick theirs
 * from it, and every call to a model goes to its provider.
 */
class Llm extends Model
{
    /** @use HasFactory<LlmFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'features' => 'json',
            'is_reasoning' => 'boolean',
            'context_window' => 'integer',
            'input_price_per_1m_tokens' => 'decimal:4',
            'output_price_per_1m_tokens' => 'decimal:4',
            'is_enabled' => 'boolean',
        ];
    }

    #[\Override]
    protected $attributes = [
        'is_reasoning' => false,
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

    /** Whether the model may be picked: it and its provider are enabled. */
    public function isAvailable(): bool
    {
        return $this->is_enabled && $this->provider->is_enabled;
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
