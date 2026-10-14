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
 * A chat provider that catalog models ({@see Llm}) belong to; calls to a
 * model go through it. A provider with an account (OpenRouter, Scaleway AI,
 * Mistral…) has one row with no key or address: it calls with the account's,
 * set once in Settings → AI providers. Only a server of your own (`custom`,
 * there may be several) keeps its address, key and extra headers on its
 * row. What a kind means (its default address, client, model list) is its
 * adapter's ({@see ProviderAdapters}).
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
     * Whether it can call: a server of your own needs no key, any other kind
     * the key of its account (Settings → AI providers).
     */
    public function isConnected(): bool
    {
        $adapters = app(ProviderAdapters::class);

        return $adapters->has($this->kind) && $adapters->for($this->kind)->isConnected($this);
    }

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
