<?php

declare(strict_types=1);

namespace Domain\Conversation\Data\Conversations;

use App\Identity\Chatter\Models\Chatter;
use Domain\Agent\Models\Agent;
use Domain\Conversation\Models\Conversation;
use Spatie\LaravelData\Data;

/**
 * Input for opening a conversation over the public API: the agent it talks
 * to, an optional chatter the conversation is attributed to (the chatter's
 * monthly message limit then applies), and an optional title.
 */
class CreateApiConversationData extends Data
{
    public function __construct(
        public Agent $agent,
        public ?Chatter $chatter = null,
        public string $title = Conversation::DEFAULT_TITLE,
    ) {}
}
