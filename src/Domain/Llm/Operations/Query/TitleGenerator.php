<?php

namespace Domain\Llm\Operations\Query;

use Domain\Llm\Data\Chat\ChatMessageData;
use Domain\Llm\Data\Chat\ChatRequestData;
use Domain\Llm\Services\LlmChat;

/**
 * Writes a short title for a conversation from its first messages, with the
 * model set in `chatvia.ai.models.title`.
 */
class TitleGenerator
{
    public function __construct(
        private readonly LlmChat $chat,
    ) {}

    public function generate(string $messages): string
    {
        return $this->chat->send(new ChatRequestData(
            model: (string) config('chatvia.ai.models.title'),
            messages: [
                ChatMessageData::system(prompt('conversation/title')),
                ChatMessageData::user('The text is: '.$messages),
            ],
            maxTokens: 50,
        ))->content;
    }
}
