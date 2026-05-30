<?php

namespace Domain\Widget\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class WidgetMarkReadResultData extends Data
{
    public function __construct(
        public int $marked_count,
    ) {}
}
