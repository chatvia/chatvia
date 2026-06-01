<?php

namespace Domain\Widget\Data\Auth;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class WidgetAgentVerifyResultData extends Data
{
    public function __construct(
        public bool $verified,
    ) {}
}
