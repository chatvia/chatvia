<?php

namespace Domain\Agents\Models;

use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

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
 * @property bool $reasoning
 * @property numeric|null $input_price_per_1m_tokens
 * @property numeric|null $output_price_per_1m_tokens
 * @property bool $is_default
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
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Llm whereIsDefault($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Llm whereLocation($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Llm whereOutputPricePer1mTokens($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Llm whereReasoning($value)
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

    protected $casts = [
        'features' => 'json',
        'reasoning' => 'boolean',
        'context_window' => 'integer',
        'credits' => 'integer',
        'input_price_per_1m_tokens' => 'decimal:4',
        'output_price_per_1m_tokens' => 'decimal:4',
    ];

    protected $attributes = [
        'reasoning' => false,
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
            'reasoning' => ['boolean'],
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
            'reasoning' => ['boolean'],
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

    public function scopeByLocation($query, $location)
    {
        return $query->where('location', $location);
    }

    public function scopeWithReasoning($query)
    {
        return $query->where('reasoning', true);
    }
}
