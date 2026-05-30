<?php

namespace Domain\Widget\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class WidgetAgentVerifyResultData extends Data
{
    public function __construct(
        public bool $verified,
    ) {}
}
