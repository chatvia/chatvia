<?php

namespace App\Http\Resources\Api\V1\Conversations;

use App\Workspace\Models\User;
use App\Billing\Enums\CapabilityEnum;
use Domain\Chat\Models\ChatConversation;
use Illuminate\Support\Facades\Auth;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\DataCollection;

/**
 * Full conversation projection (header + messages) for the API show endpoint.
 *
 * Sentiment + tone are plan-gated; the bearer-token caller is the
 * workspace owner, so their plan decides whether those columns surface.
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
        /** @var User|null $owner */
        $owner = Auth::user();

        // Project specific columns + hard limit; conversations can grow into
        // the thousands and a full dump is a memory bomb.
        $messages = $conversation->messages()
            ->select(['id', 'role', 'content', 'created_at', 'sentiment', 'tone'])
            ->orderBy('id')
            ->limit(500)
            ->get()
            ->map(fn ($m): ChatConversationMessageData => ChatConversationMessageData::fromModel($m));

        return new self(
            id: $conversation->external_id,
            title: $conversation->title,
            sentiment: $owner?->hasCapability(CapabilityEnum::SENTIMENT) ? $conversation->sentiment : null,
            tone: $owner?->hasCapability(CapabilityEnum::TONE) ? $conversation->tone : null,
            handoff_status: $conversation->handoff_status,
            created_at: $conversation->created_at?->toIso8601String(),
            messages: new DataCollection(ChatConversationMessageData::class, $messages),
        );
    }
}
