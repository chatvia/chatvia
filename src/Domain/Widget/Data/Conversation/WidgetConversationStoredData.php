<?php

declare(strict_types=1);

namespace Domain\Widget\Data\Conversation;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * The external id of a conversation the widget just started.
 */
#[TypeScript]
class WidgetConversationStoredData extends Data
{
    public function __construct(
        public string $id,
    ) {}
}
