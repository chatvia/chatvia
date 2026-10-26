<?php

declare(strict_types=1);

namespace Domain\Conversation\Data\Messages;

use Domain\Conversation\Data\Tooling\PendingToolCallData;
use Domain\Conversation\Enums\MessageStepKind;
use Domain\Integration\Tool\Enums\ToolKind;
use Spatie\LaravelData\Data;

/**
 * One step an agent took before its answer, as the chat shows it in the step
 * chain: what the model wrote before calling a tool ("Let me look up your
 * event types."), or a tool call it made, with the name and logo the chat
 * shows for it. Saved in `chat_messages.metadata.steps` so the chain comes
 * back on reload; the tool's arguments and result are never kept here.
 *
 * Property names are snake_case because the object is queued inside
 * StoreMessageJob with {@see AssistantTurnData}.
 */
class MessageStepData extends Data
{
    public function __construct(
        public MessageStepKind $kind,
        public ?string $text = null,
        public ?string $label = null,
        public ?ToolKind $tool_kind = null,
        public ?string $icon_url = null,
    ) {}

    public static function text(string $text): self
    {
        return new self(kind: MessageStepKind::Text, text: $text);
    }

    public static function tool(PendingToolCallData $call): self
    {
        return new self(
            kind: MessageStepKind::Tool,
            label: $call->label ?? $call->name,
            tool_kind: $call->kind,
            icon_url: $call->iconUrl,
        );
    }
}
