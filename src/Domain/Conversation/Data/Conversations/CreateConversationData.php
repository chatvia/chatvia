<?php

declare(strict_types=1);

namespace Domain\Conversation\Data\Conversations;

use Domain\Conversation\Actions\Conversations\CreateConversationAction;
use Domain\Conversation\Data\Memory\ChatScopeData;
use Domain\Conversation\Models\Attachment;
use Spatie\LaravelData\Data;

/**
 * A hosted-chat visitor's first message, which opens a new conversation
 * ({@see CreateConversationAction}).
 */
class CreateConversationData extends Data
{
    /**
     * @param  array<int, string>  $attachmentIds  external ids of {@see Attachment} rows uploaded before the conversation existed; the new conversation claims them
     */
    public function __construct(
        public ChatScopeData $scope,
        public string $message,
        public ?string $ipAddress = null,
        public ?string $userAgent = null,
        public ?string $referrerUrl = null,
        public ?string $pageUrl = null,
        public ?string $locale = null,
        public array $attachmentIds = [],
        // The signed-in chatter, or null for owner/teammate senders. Stamps
        // the session's user (per-chatter limit and analytics).
        public ?int $chatterUserId = null,
    ) {}
}
