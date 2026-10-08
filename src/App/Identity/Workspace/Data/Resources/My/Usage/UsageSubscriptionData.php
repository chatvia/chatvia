<?php

namespace App\Identity\Workspace\Data\Resources\My\Usage;

use DateTimeInterface;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class UsageSubscriptionData extends Data
{
    public function __construct(
        public string $plan,
        // Null when the account has no active credit cycle (for example when
        // billing is turned off).
        public ?DateTimeInterface $started_at,
        public ?DateTimeInterface $ends_at,
    ) {}
}
