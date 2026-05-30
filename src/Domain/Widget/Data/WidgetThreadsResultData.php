<?php

namespace Domain\Widget\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Result of fetching (or auto-provisioning) the visitor's threads for an
 * agent. The threads array is whatever shape ChatMemory hands back —
 * historically a list of `{id, title, timestamp, visible}`.
 */
#[TypeScript]
class WidgetThreadsResultData extends Data
{
    public function __construct(
        /** @var array<int, mixed> */
        public array $threads,
    ) {}
}
