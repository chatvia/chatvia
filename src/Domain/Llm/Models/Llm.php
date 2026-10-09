<?php

namespace Domain\Llm\Models;

use Database\Factories\LlmFactory;
use Domain\Llm\Enums\LlmProviderEnum;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * @property int $id
 * @property string $internal_name
 * @property string $external_name
 * @property int $credits
 * @property string $descriptive_name
 * @property string $description
 * @property string $icon
 * @property int $context_window
 * @property array<string, bool> $features
 * @property string $location
 * @property bool $is_reasoning
 * @property numeric|null $input_price_per_1m_tokens
 * @property numeric|null $output_price_per_1m_tokens
 * @property LlmProviderEnum $provider
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @method static \Database\Factories\LlmFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Llm newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Llm newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Llm query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Llm whereContextWindow($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Llm whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Llm whereCredits($value)
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
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Llm whereProvider($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Llm whereIsReasoning($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Llm whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
#[Guarded([
    'id',
])]
/**
 * A model in the LLM catalog: its names, provider, context window, features
 * and message-credit price. Agents pick their chat model from it.
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
            'credits' => 'integer',
            'input_price_per_1m_tokens' => 'decimal:4',
            'output_price_per_1m_tokens' => 'decimal:4',
            'provider' => LlmProviderEnum::class,
        ];
    }

    #[\Override]
    protected $attributes = [
        'is_reasoning' => false,
        'credits' => 1,
    ];

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
        return Cache::rememberForever('llms.catalog', fn () => self::query()->get());
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
