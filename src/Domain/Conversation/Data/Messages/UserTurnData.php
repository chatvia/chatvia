<?php

namespace Domain\Conversation\Data\Messages;

use Carbon\Carbon;
use Domain\Conversation\Actions\Messages\StoreUserMessageAction;
use Spatie\LaravelData\Data;

/**
 * One visitor message as the chat engine carries it through a turn: the text
 * that is stored and shown (PII already masked), the text the LLM sees, and
 * the request facts (IP, country, user agent, pages, locale) that are written
 * onto the session when the message is stored by {@see StoreUserMessageAction}.
 *
 * Property names are snake_case because the object is queued inside
 * StoreMessageJob; renaming one would break jobs already in the queue.
 */
class UserTurnData extends Data
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
     * Copy with a different LLM-facing message. Knowledge retrieval uses it to
     * add the reference material; every other field carries over untouched,
     * so a new field can never be silently dropped by a hand-written rebuild.
     */
    public function withMessageForLlm(?string $messageForLlm): self
    {
        $clone = clone $this;
        $clone->message_for_llm = $messageForLlm;

        return $clone;
    }
}
