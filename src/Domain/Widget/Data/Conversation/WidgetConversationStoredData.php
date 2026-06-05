<?php

namespace Domain\Widget\Data\Conversation;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class WidgetConversationStoredData extends Data
{
    public function __construct(
        public string $id,
    ) {}
}
