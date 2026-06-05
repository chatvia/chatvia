<?php

namespace Domain\Widget\Data\Conversation;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class WidgetMarkReadResultData extends Data
{
    public function __construct(
        public int $marked_count,
    ) {}
}
