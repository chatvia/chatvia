<?php

namespace Domain\Agent\Data\Resources\ChatLog\Conversations;

use Domain\Conversation\Enums\ConversationSource;
use Domain\Conversation\Models\Conversation;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class ChatLogConversationData extends Data
{
    public function __construct(
        public string $conversation_id,
        public string $last_at,
        public ?string $expires_at,
        public int $messages_count,
        public ?string $first_message_excerpt,
        public ?string $title,
        public ConversationSource $source,
        public ?string $country,
        public ?string $country_name,
        public ?string $user_agent,
        public ?string $device_type,
        public ?string $browser,
        public ?string $os,
        public ?string $city,
        public ?string $referrer_url,
        public ?string $page_url,
        public ?string $locale,
        /** @var string[] */
        public array $topics,
        public ?string $sentiment,
        public ?string $tone,
    ) {}

    public static function fromConversation(Conversation $conversation, ?string $excerpt = null): self
    {
        return new self(
            conversation_id: $conversation->external_id,
            last_at: $conversation->updated_at->toISOString(),
            expires_at: $conversation->expires_at?->toISOString(),
            messages_count: $conversation->messages_count ?? 0,
            first_message_excerpt: $excerpt,
            title: $conversation->title,
            source: $conversation->source,
            country: $conversation->session->country?->code,
            country_name: $conversation->session->country?->name,
            user_agent: $conversation->session->user_agent,
            device_type: $conversation->session->device_type,
            browser: $conversation->session->browser,
            os: $conversation->session->os,
            city: $conversation->session->city,
            referrer_url: $conversation->session->referrer_url,
            page_url: $conversation->session->page_url,
            locale: $conversation->session->locale,
            topics: $conversation->topics->pluck('topic')->values()->all(),
            sentiment: $conversation->sentiment,
            tone: $conversation->tone,
        );
    }
}
