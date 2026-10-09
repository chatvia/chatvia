<?php

namespace Domain\Widget\Data\Conversation;

use Domain\Conversation\Data\Memory\ConversationData;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * The visitor's conversations for an agent, as the widget lists them. Each
 * entry is the array form of {@see ConversationData} (`id`, `title`,
 * `timestamp`, `is_visible`, `messages`).
 *
 * The property name must stay `conversations`: the widget script reads
 * `data.conversations` directly, and an unknown key would look like an empty
 * list, so the widget would start a new conversation on every page load.
 */
#[TypeScript]
class WidgetConversationsResultData extends Data
{
    public function __construct(
        /** @var array<int, mixed> */
        public array $conversations,
    ) {}
}
