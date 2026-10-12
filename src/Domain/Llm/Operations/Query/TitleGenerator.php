<?php

declare(strict_types=1);

namespace Domain\Llm\Operations\Query;

use Domain\Llm\Data\Chat\ChatMessageData;
use Domain\Llm\Data\Chat\ChatRequestData;
use Domain\Llm\Enums\LlmRole;
use Domain\Llm\Services\LlmChat;
use Domain\Llm\Services\ModelRoles;

/**
 * Writes a short title for a conversation from its first messages, with the
 * Fast model ({@see LlmRole}).
 */
class TitleGenerator
{
    public function __construct(
        private readonly LlmChat $chat,
        private readonly ModelRoles $models,
    ) {}

    public function generate(string $messages): string
    {
        return $this->chat->send(new ChatRequestData(
            model: $this->models->model(LlmRole::Fast),
            messages: [
                ChatMessageData::system(prompt('conversation/title')),
                ChatMessageData::user('The text is: '.$messages),
            ],
            maxTokens: 50,
        ))->content;
    }
}
