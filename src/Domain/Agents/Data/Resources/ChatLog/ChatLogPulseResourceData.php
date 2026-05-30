<?php

namespace Domain\Agents\Data\Resources\ChatLog;

use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\DataCollection;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Complete payload behind the chat-log pulse view: every visitor message's
 * tone/sentiment snapshot, the summary (start/end/peaks/trend), and the
 * conversation-level vitals.
 */
#[TypeScript]
class ChatLogPulseResourceData extends Data
{
    public function __construct(
        /** @var DataCollection<int, ChatLogPulseMessageData> */
        #[DataCollectionOf(ChatLogPulseMessageData::class)]
        public DataCollection $messages,
        public ChatLogPulseSummaryData $summary,
        public ChatLogPulseConversationData $conversation,
    ) {}
}
