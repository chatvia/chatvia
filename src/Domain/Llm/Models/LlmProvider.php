<?php

declare(strict_types=1);

namespace Domain\Llm\Models;

use Database\Factories\LlmProviderFactory;
use Domain\Llm\Enums\LlmProviderEnum;
use Domain\Llm\Services\ProviderAdapters;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property LlmProviderEnum $kind
 * @property string $name
 * @property string|null $base_url
 * @property string|null $api_key
 * @property array<string, string>|null $headers
 * @property bool $is_enabled
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @method static \Database\Factories\LlmProviderFactory factory($count = null, $state = [])
 *
 * @mixin \Eloquent
 */
#[Guarded(['id'])]
#[Hidden(['api_key', 'headers'])]
/**
 * A connection to an LLM provider that an admin set up: its kind, address,
 * API key and extra headers. An install can have several, of the same kind
 * too (two OpenAI-compatible servers, say). Every catalog model ({@see Llm})
 * belongs to one, and calls to the model go through it. What a kind means
 * (its default address, client, model list) is its adapter's
 * ({@see ProviderAdapters}).
 */
class LlmProvider extends Model
{
    /** @use HasFactory<LlmProviderFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'kind' => LlmProviderEnum::class,
            'api_key' => 'encrypted',
            'headers' => 'encrypted:array',
            'is_enabled' => 'boolean',
        ];
    }

    #[\Override]
    protected $attributes = [
        'is_enabled' => true,
    ];

    /**
     * @return HasMany<Llm, $this>
     */
    public function llms(): HasMany
    {
        return $this->hasMany(Llm::class);
    }

    protected static function booted(): void
    {
        // The cached catalog carries each model's provider.
        static::saved(fn () => Llm::flushCatalogCache());
        static::deleted(fn () => Llm::flushCatalogCache());
    }
}
