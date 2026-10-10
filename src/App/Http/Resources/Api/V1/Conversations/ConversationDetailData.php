<?php

namespace App\Http\Resources\Api\V1\Conversations;

use Domain\Conversation\Models\Conversation;
use Domain\Conversation\Models\Message;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\DataCollection;

/**
 * Full conversation projection (header + messages) for the API show endpoint.
 */
class ConversationDetailData extends Data
{
    public function __construct(
        public string $id,
        public ?string $title,
        public ?string $sentiment,
        public ?string $tone,
        public ?string $handoff_status,
        public ?string $created_at,
        /** @var DataCollection<int, ConversationMessageData> */
        #[DataCollectionOf(ConversationMessageData::class)]
        public DataCollection $messages,
    ) {}

    public static function fromModel(Conversation $conversation): self
    {
        // Project specific columns + hard limit; conversations can grow into
        // the thousands and a full dump is a memory bomb.
        $messages = $conversation->messages()
            ->select(['id', 'role', 'content', 'created_at', 'sentiment', 'tone'])
            ->orderBy('id')
            ->limit(500)
            ->get()
            ->map(fn (Message $m): ConversationMessageData => ConversationMessageData::fromModel($m));

        return new self(
            id: $conversation->external_id,
            title: $conversation->title,
            sentiment: $conversation->sentiment,
            tone: $conversation->tone,
            handoff_status: $conversation->handoff_status?->value,
            created_at: $conversation->created_at?->toIso8601String(),
            messages: new DataCollection(ConversationMessageData::class, $messages),
        );
    }
}
