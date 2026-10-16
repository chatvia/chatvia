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
        public readonly string $external_session_id,
        public readonly string $external_conversation_id,
        public readonly string $external_message_id,
        public readonly string $model,
        public readonly string $message,
        public readonly Carbon $sent_at,
        public readonly ?string $message_for_llm = null,
        // The signed-in chatter sending the turn (chatters.id), or null.
        public readonly ?int $chatter_id = null,
        public readonly ?string $language = null,
        public readonly ?string $ip_address = null,
        // Lowercase ISO-3166 alpha-2, resolved at request time (CF-aware). Carried
        // to the session so its country comes from Cloudflare's view of the real
        // visitor, not a geo lookup of the CF edge IP on the queued locate listener.
        public readonly ?string $country = null,
        public readonly ?string $user_agent = null,
        public readonly ?string $referrer_url = null,
        public readonly ?string $page_url = null,
        public readonly ?string $locale = null,
        public readonly ?string $source = null,
        public readonly array $attachments = [],
    ) {}

    /**
     * Copy with a different LLM-facing message. Knowledge retrieval uses it
     * to add the reference material; every other field carries over.
     */
    public function withMessageForLlm(?string $messageForLlm): self
    {
        return new self(
            external_session_id: $this->external_session_id,
            external_conversation_id: $this->external_conversation_id,
            external_message_id: $this->external_message_id,
            model: $this->model,
            message: $this->message,
            sent_at: $this->sent_at,
            message_for_llm: $messageForLlm,
            chatter_id: $this->chatter_id,
            language: $this->language,
            ip_address: $this->ip_address,
            country: $this->country,
            user_agent: $this->user_agent,
            referrer_url: $this->referrer_url,
            page_url: $this->page_url,
            locale: $this->locale,
            source: $this->source,
            attachments: $this->attachments,
        );
    }
}
