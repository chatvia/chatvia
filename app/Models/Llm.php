<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

class Llm extends Model
{
    use HasFactory;

    protected $guarded = [
        'id',
    ];

    protected $casts = [
        'features' => 'json',
        'requires_paid_plan' => 'boolean',
        'reasoning' => 'boolean',
        'context_window' => 'integer',
        'credits' => 'integer',
        'input_price_per_1m_tokens' => 'decimal:4',
        'output_price_per_1m_tokens' => 'decimal:4',
    ];

    protected $attributes = [
        'reasoning' => false,
        'requires_paid_plan' => false,
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
            'requires_paid_plan' => ['boolean'],
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
            'requires_paid_plan' => ['boolean'],
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

    public function isAvailableForUser($user = null): bool
    {
        if (! $this->requires_paid_plan) {
            return true;
        }

        if (! $user) {
            return false;
        }

        // Check if user has an active subscription
        return $user->subscribed() || $user->onTrial();
    }

    public function scopeAvailableForUser($query, $user = null)
    {
        if (! $user || $user->subscribed() || $user->onTrial()) {
            return $query;
        }

        return $query->where('requires_paid_plan', false);
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
