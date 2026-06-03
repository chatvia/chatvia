<?php

namespace Domain\Conversation\Data\Conversations;

use Domain\Agent\Models\Agent;
use Domain\Conversation\Actions\Conversations\CreateConversationAction;
use Domain\Conversation\Models\Attachment;
use Spatie\LaravelData\Data;

class CreateConversationData extends Data
{
    /**
     * @param  array<int, string>  $attachmentIds  External UUIDs of orphan
     *                                             {@see Attachment}
     *                                             rows uploaded before the conversation
     *                                             existed — claimed by
     *                                             {@see CreateConversationAction}.
     */
    public function __construct(
        public Agent $agent,
        public string $message,
        public ?string $ipAddress = null,
        public ?string $userAgent = null,
        public ?string $referrerUrl = null,
        public ?string $pageUrl = null,
        public ?string $locale = null,
        public array $attachmentIds = [],
    ) {}
}
