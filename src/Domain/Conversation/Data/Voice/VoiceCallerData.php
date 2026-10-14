<?php

declare(strict_types=1);

namespace Domain\Conversation\Data\Voice;

use Domain\Agent\Models\Agent;
use Domain\Conversation\Enums\ConversationSource;
use Domain\Conversation\Services\Streaming\Requests\StreamRequestDataFactory;
use Spatie\LaravelData\Data;

/**
 * Who asks for a voice call and where: the agent, the visitor's session and
 * conversation, the signed-in chatter (their message limit applies) or staff
 * account (the audit actor), the surface, and the request facts stored on the
 * session. Built from the HTTP request by {@see StreamRequestDataFactory}.
 */
class VoiceCallerData extends Data
{
    public function __construct(
        public Agent $agent,
        public ConversationSource $surface,
        public string $sessionId,
        public string $conversationId,
        public ?int $chatterId = null,
        public ?string $chatterName = null,
        public ?string $chatterExternalId = null,
        public ?int $actorUserId = null,
        public ?string $actorName = null,
        // `playground` for a dashboard preview, else null (as on a typed turn).
        public ?string $source = null,
        public ?string $ipAddress = null,
        public ?string $country = null,
        public ?string $userAgent = null,
        public ?string $referrerUrl = null,
        public ?string $pageUrl = null,
        public ?string $locale = null,
    ) {}
}
