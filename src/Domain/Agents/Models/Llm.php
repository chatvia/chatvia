<?php

namespace Domain\Agents\Models;

use Domain\Agents\Enums\LlmProviderEnum;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

/**
 * @property int $id
 * @property string $internal_name
 * @property string $external_name
 * @property int $credits
 * @property string $descriptive_name
 * @property string $description
 * @property string $icon
 * @property int $context_window
 * @property array<array-key, mixed> $features
 * @property string $location
 * @property bool $is_reasoning
 * @property numeric|null $input_price_per_1m_tokens
 * @property numeric|null $output_price_per_1m_tokens
 * @property LlmProviderEnum $provider
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read string $location_label
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Llm byLocation($location)
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
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Llm withReasoning()
 *
 * @mixin \Eloquent
 */
#[Guarded([
    'id',
])]
class Llm extends Model
{
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

    protected $attributes = [
        'is_reasoning' => false,
        'credits' => 1,
    ];

    public static function rules(): array
    {
        return [
            'internal_name' => ['required', 'string', 'max:255', 'unique:llms,internal_name'],
            'external_name' => ['required', 'string', 'max:255'],
            'descriptive_name' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string'],
            'icon' => ['required', 'string', 'max:255'],
            'context_window' => ['required', 'integer', 'min:1'],
            'features' => ['nullable', 'array'],
            'location' => ['required', Rule::in(['us', 'eu', 'cn'])],
            'is_reasoning' => ['boolean'],
            'provider' => ['required', new Enum(LlmProviderEnum::class)],
        ];
    }

    public static function updateRules($id): array
    {
        return [
            'internal_name' => ['required', 'string', 'max:255', Rule::unique('llms', 'internal_name')->ignore($id)],
            'external_name' => ['required', 'string', 'max:255'],
            'descriptive_name' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string'],
            'icon' => ['required', 'string', 'max:255'],
            'context_window' => ['required', 'integer', 'min:1'],
            'features' => ['nullable', 'array'],
            'location' => ['required', Rule::in(['us', 'eu', 'cn'])],
            'is_reasoning' => ['boolean'],
            'provider' => ['required', new Enum(LlmProviderEnum::class)],
        ];
    }

    public function getLocationLabelAttribute(): string
    {
        return match ($this->location) {
            'us' => 'United States',
            'eu' => 'Europe',
            'cn' => 'China',
            default => $this->location,
        };
    }

    #[Scope]
    protected function byLocation(Builder $query, string $location): void
    {
        $query->where('location', $location);
    }

    #[Scope]
    protected function withReasoning(Builder $query): void
    {
        $query->where('is_reasoning', true);
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
