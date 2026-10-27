<?php

declare(strict_types=1);

namespace App\Http\Resources\Inbox;

use App\Identity\Workspace\Models\User;
use Domain\Conversation\Enums\ConversationSource;
use Domain\Conversation\Enums\HandoffStatus;
use Domain\Conversation\Enums\MessageRole;
use Domain\Conversation\Models\Conversation;
use Domain\Conversation\QueryBuilders\ConversationQueryBuilder;
use Illuminate\Support\Str;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * One row of the Inbox: who is chatting, with which agent, where the chat
 * stands (AI, waiting, with a person) and whether the viewer may join it.
 * `can_join` covers the surface and the viewer's rights; a chat another
 * operator holds still shows `holder_name`, and the page locks the reply box
 * while that lasts. `visitor_name` is null for an anonymous visitor; the
 * page names them.
 */
#[TypeScript]
class InboxConversationData extends Data
{
    /** The row shows this many characters of the last message. */
    private const int EXCERPT_LENGTH = 140;

    public function __construct(
        public string $conversation_id,
        public InboxAgentData $agent,
        public ?string $visitor_name,
        public ?string $visitor_email,
        public ?string $country,
        public ConversationSource $source,
        public HandoffStatus $handoff_status,
        public bool $held_by_me,
        public ?string $holder_name,
        public ?string $last_message,
        public ?MessageRole $last_message_role,
        public ?string $last_activity_at,
        public bool $is_online,
        public bool $can_join,
    ) {}

    /**
     * @param  bool  $mayJoinOnAgent  the viewer's handoff rights on the agent; the surface check is added here
     */
    public static function fromConversation(Conversation $conversation, User $viewer, bool $mayJoinOnAgent): self
    {
        $lastMessage = $conversation->lastMessage;
        $heldByMe = $conversation->isHeldBy($viewer);
        $heldByOther = $conversation->isHandedOff() && ! $heldByMe;

        return new self(
            conversation_id: $conversation->external_id,
            agent: InboxAgentData::fromAgent($conversation->agent),
            visitor_name: $conversation->displayName(),
            visitor_email: $conversation->displayEmail(),
            country: $conversation->session->country?->code,
            source: $conversation->source,
            handoff_status: $conversation->handoff_status ?? HandoffStatus::Ai,
            held_by_me: $heldByMe,
            holder_name: $heldByOther ? $conversation->handoffUser?->name : null,
            last_message: $lastMessage?->content === null ? null : Str::of($lastMessage->content)->squish()->limit(self::EXCERPT_LENGTH)->toString(),
            last_message_role: $lastMessage?->role,
            last_activity_at: $conversation->last_activity_at?->toIso8601String(),
            is_online: $conversation->last_activity_at?->isAfter(now()->subMinutes(ConversationQueryBuilder::ONLINE_WINDOW_MINUTES)) ?? false,
            // The holder keeps replying when live chat goes off (a lapsed key,
            // the toggle): a chat a person holds finishes.
            can_join: ($mayJoinOnAgent || $heldByMe) && $conversation->isSourceWidget(),
        );
    }
}
