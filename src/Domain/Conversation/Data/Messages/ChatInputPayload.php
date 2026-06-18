<?php

namespace Domain\Conversation\Data\Messages;

use Carbon\Carbon;
use Spatie\LaravelData\Data;

class ChatInputPayload extends Data
{
    /**
     * @param  array<int, array{id: string, url: string, filename: string, mime_type: string, size_bytes: int}>  $attachments
     */
    public function __construct(
        public string $external_session_id,
        public string $external_conversation_id,
        public string $external_message_id,
        public string $model,
        public string $message,
        public Carbon $sent_at,
        public array $updated_message_info = [],
        public ?string $message_for_llm = null,
        public ?int $user_id = null,
        public ?string $language = null,
        public ?string $ip_address = null,
        // Lowercase ISO-3166 alpha-2, resolved at request time (CF-aware). Carried
        // to the session so its country comes from Cloudflare's view of the real
        // visitor, not a geo lookup of the CF edge IP on the queued locate listener.
        public ?string $country = null,
        public ?string $user_agent = null,
        public ?string $referrer_url = null,
        public ?string $page_url = null,
        public ?string $locale = null,
        public ?string $source = null,
        public array $attachments = [],
    ) {}

    /**
     * Immutable copy with a different LLM-facing message. Used when knowledge
     * retrieval enriches the user turn with retrieved context: every other
     * field carries over untouched, so a newly-added field can never be
     * silently dropped by a hand-maintained field-by-field rebuild.
     */
    public function withMessageForLlm(?string $messageForLlm): self
    {
        $clone = clone $this;
        $clone->message_for_llm = $messageForLlm;

        return $clone;
    }
}
