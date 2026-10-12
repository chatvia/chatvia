<?php

declare(strict_types=1);

namespace Domain\Llm\Operations\Documents;

use Domain\Llm\Data\Chat\ChatMessageData;
use Domain\Llm\Data\Chat\ChatRequestData;
use Domain\Llm\Enums\LlmRole;
use Domain\Llm\Services\LlmChat;
use Domain\Llm\Services\ModelRoles;

/**
 * Turns the Markdown tables found on a document page into plain prose, so the
 * table content can be embedded and found by search (the Smart model,
 * {@see LlmRole}).
 */
class Tables
{
    public function __construct(
        private readonly LlmChat $chat,
        private readonly ModelRoles $models,
    ) {}

    /**
     * @param  list<string>  $tables  Markdown tables
     */
    public function describe(array $tables): string
    {
        return $this->chat->send(new ChatRequestData(
            model: $this->models->model(LlmRole::Smart),
            messages: [
                ChatMessageData::user(prompt('tables/describe', ['tables' => implode("\n\n", $tables)])),
            ],
            temperature: 0.0,
            maxTokens: 512,
        ))->content;
    }
}
