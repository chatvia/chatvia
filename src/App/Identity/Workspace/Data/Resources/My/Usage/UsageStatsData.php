<?php

namespace App\Identity\Workspace\Data\Resources\My\Usage;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class UsageStatsData extends Data
{
    public function __construct(
        public int $limit,
        public int $used,
        public int $remaining,
    ) {}
}
