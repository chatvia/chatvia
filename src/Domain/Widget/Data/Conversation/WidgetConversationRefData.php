<?php

declare(strict_types=1);

namespace Domain\Widget\Data\Conversation;

use Spatie\LaravelData\Data;

/**
 * Names one conversation of a widget visitor: the conversation id the call is
 * about plus the visitor's session id. Both come from the widget and are only
 * trusted after the conversation is found under that session and agent.
 */
class WidgetConversationRefData extends Data
{
    public function __construct(
        public string $conversationId,
        public ?string $sessionId,
    ) {}
}
