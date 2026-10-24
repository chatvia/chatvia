<?php

declare(strict_types=1);

namespace App\Identity\Workspace\Data\Account;

use App\Identity\Workspace\Enums\SocialProvider;
use App\Identity\Workspace\Models\UserSocial;
use Illuminate\Support\Str;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * One social sign-in provider on the account's Linked accounts page: whether
 * the user has connected it, and when.
 */
#[TypeScript]
class SocialAccountRowData extends Data
{
    public function __construct(
        public string $provider,
        public string $label,
        public bool $connected,
        public ?string $connected_at,
    ) {}

    public static function fromProvider(SocialProvider $provider, ?UserSocial $linked): self
    {
        return new self(
            provider: $provider->value,
            label: Str::ucfirst($provider->value),
            connected: $linked instanceof UserSocial,
            connected_at: $linked?->created_at?->toIso8601String(),
        );
    }
}
