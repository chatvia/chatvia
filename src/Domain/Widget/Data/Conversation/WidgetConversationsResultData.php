<?php

namespace Domain\Widget\Data\Conversation;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Result of fetching (or auto-provisioning) the visitor's conversations
 * for an agent. The conversations array is whatever shape ChatMemory
 * hands back — historically a list of `{id, title, timestamp, visible}`.
 *
 * Property name MUST stay `conversations`: the widget client (storage.js)
 * reads `data.conversations` directly. The recent Thread→Conversation
 * rename missed this DTO, which made the widget refresh always create a
 * brand-new "New Chat" because it saw an empty list.
 */
#[TypeScript]
class WidgetConversationsResultData extends Data
{
    public function __construct(
        /** @var array<int, mixed> */
        public array $conversations,
    ) {}
}
