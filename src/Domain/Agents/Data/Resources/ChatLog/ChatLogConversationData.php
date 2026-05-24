<?php

namespace Domain\Agents\Data\Resources\ChatLog;

use Domain\Chat\Models\ChatConversation;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class ChatLogConversationData extends Data
{
    public function __construct(
        public string $conversation_id,
        public string $last_at,
        public int $messages_count,
        public ?string $first_message_excerpt,
        public ?string $title,
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
        public ?string $topic,
        public ?string $topic_id,
        public ?string $sentiment,
        public ?string $tone,
    ) {}

    public static function fromConversation(ChatConversation $conversation, ?string $excerpt = null): self
    {
        return new self(
            conversation_id: $conversation->external_id,
            last_at: $conversation->updated_at->toISOString(),
            messages_count: $conversation->messages_count ?? 0,
            first_message_excerpt: $excerpt,
            title: $conversation->title,
            country: $conversation->session?->country?->code,
            country_name: $conversation->session?->country?->name,
            user_agent: $conversation->session?->user_agent,
            device_type: $conversation->session?->device_type,
            browser: $conversation->session?->browser,
            os: $conversation->session?->os,
            city: $conversation->session?->city,
            referrer_url: $conversation->session?->referrer_url,
            page_url: $conversation->session?->page_url,
            locale: $conversation->session?->locale,
            topic: $conversation->topic?->topic,
            topic_id: $conversation->chat_topic_id,
            sentiment: $conversation->sentiment,
            tone: $conversation->tone,
        );
    }
}
