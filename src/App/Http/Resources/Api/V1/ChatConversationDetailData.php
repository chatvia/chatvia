<?php

namespace App\Http\Resources\Api\V1;

use Domain\Chat\Models\ChatConversation;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\DataCollection;

/**
 * Full conversation projection (header + messages) for the API show endpoint.
 */
class ChatConversationDetailData extends Data
{
    public function __construct(
        public string $id,
        public ?string $title,
        public ?string $sentiment,
        public ?string $tone,
        public ?string $handoff_status,
        public ?string $created_at,
        /** @var DataCollection<int, ChatConversationMessageData> */
        #[DataCollectionOf(ChatConversationMessageData::class)]
        public DataCollection $messages,
    ) {}

    public static function fromModel(ChatConversation $conversation): self
    {
        $messages = $conversation->messages()->orderBy('id')->get()
            ->map(fn ($m): ChatConversationMessageData => ChatConversationMessageData::fromModel($m));

        return new self(
            id: $conversation->external_id,
            title: $conversation->title,
            sentiment: $conversation->sentiment,
            tone: $conversation->tone,
            handoff_status: $conversation->handoff_status,
            created_at: $conversation->created_at?->toIso8601String(),
            messages: new DataCollection(ChatConversationMessageData::class, $messages),
        );
    }
}
