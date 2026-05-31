<?php

namespace App\Http\Resources\Api\V1;

use App\Auth\Models\User;
use App\Billing\Enums\CapabilityEnum;
use Domain\Chat\Models\ChatConversation;
use Illuminate\Support\Facades\Auth;
use Spatie\LaravelData\Data;

/**
 * Lightweight conversation projection for the API list endpoint.
 *
 * Sentiment + tone are plan-gated capabilities. The bearer-token caller is
 * the workspace owner (Auth::user()), so we look at their plan to decide
 * whether to surface those columns — Starter customers see null even if
 * the backfill happened on a previous Pro subscription.
 */
class ChatConversationSummaryData extends Data
{
    public function __construct(
        public string $id,
        public ?string $title,
        public ?string $sentiment,
        public ?string $tone,
        public ?string $handoff_status,
        public ?string $last_activity_at,
        public ?string $created_at,
    ) {}

    public static function fromModel(ChatConversation $conversation): self
    {
        /** @var User|null $owner */
        $owner = Auth::user();

        return new self(
            id: $conversation->external_id,
            title: $conversation->title,
            sentiment: $owner?->hasCapability(CapabilityEnum::SENTIMENT) ? $conversation->sentiment : null,
            tone: $owner?->hasCapability(CapabilityEnum::TONE) ? $conversation->tone : null,
            handoff_status: $conversation->handoff_status,
            last_activity_at: $conversation->last_activity_at?->toIso8601String(),
            created_at: $conversation->created_at?->toIso8601String(),
        );
    }
}
