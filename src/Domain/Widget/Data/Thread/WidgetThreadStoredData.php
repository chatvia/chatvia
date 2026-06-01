<?php

namespace Domain\Widget\Data\Thread;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class WidgetThreadStoredData extends Data
{
    public function __construct(
        public string $id,
    ) {}
}
