<?php

declare(strict_types=1);

namespace Domain\Llm\Operations\Documents;

use Domain\Llm\Data\Chat\ChatMessageData;
use Domain\Llm\Data\Chat\ChatRequestData;
use Domain\Llm\Services\LlmChat;

/**
 * Turns the Markdown tables found on a document page into plain prose, so the
 * table content can be embedded and found by search (model:
 * `chatvia.ai.models.tables`).
 */
class Tables
{
    public function __construct(
        private readonly LlmChat $chat,
    ) {}

    /**
     * @param  list<string>  $tables  Markdown tables
     */
    public function describe(array $tables): string
    {
        return $this->chat->send(new ChatRequestData(
            model: (string) config('chatvia.ai.models.tables'),
            messages: [
                ChatMessageData::user(prompt('tables/describe', ['tables' => implode("\n\n", $tables)])),
            ],
            temperature: 0.0,
            maxTokens: 512,
        ))->content;
    }
}
