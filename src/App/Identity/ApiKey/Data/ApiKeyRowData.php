<?php

declare(strict_types=1);

namespace App\Identity\ApiKey\Data;

use Laravel\Sanctum\PersonalAccessToken;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * One API key on the account's API keys page. The token value itself is never
 * shown again after creation.
 */
#[TypeScript]
class ApiKeyRowData extends Data
{
    /**
     * @param  list<string>|null  $abilities
     */
    public function __construct(
        public int $id,
        public string $name,
        public ?array $abilities,
        public ?string $last_used_at,
        public ?string $created_at,
    ) {}

    public static function fromToken(PersonalAccessToken $token): self
    {
        return new self(
            id: $token->id,
            name: $token->name,
            abilities: $token->abilities,
            last_used_at: $token->last_used_at?->diffForHumans(),
            created_at: $token->created_at?->toFormattedDateString(),
        );
    }
}
