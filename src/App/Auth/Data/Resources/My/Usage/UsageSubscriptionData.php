<?php

namespace App\Auth\Data\Resources\My\Usage;

use DateTimeInterface;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class UsageSubscriptionData extends Data
{
    public function __construct(
        public string $plan,
        public DateTimeInterface $started_at,
        public DateTimeInterface $ends_at,
    ) {}
}
