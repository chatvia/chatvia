<?php

declare(strict_types=1);

namespace Domain\Llm\Operations\Documents;

use Domain\Llm\Data\Chat\ChatMessageData;
use Domain\Llm\Data\Chat\ChatRequestData;
use Domain\Llm\Enums\AiCallTag;
use Domain\Llm\Enums\LlmTask;
use Domain\Llm\Services\LlmChat;
use Domain\Llm\Services\TaskModels;
use Illuminate\Support\Arr;

/**
 * Turns the Markdown tables found on a document page into plain prose, so the
 * table content can be embedded and found by search (the Tables model,
 * {@see LlmTask::Tables}).
 */
class Tables
{
    /**
     * Room for the reply. A page can hold several tables, and a reasoning
     * model (gpt-oss, gpt-5…) spends tokens thinking before it writes: with a
     * tight cap the description is cut short or comes back empty.
     */
    private const int MAX_TOKENS = 2048;

    public function __construct(
        private readonly LlmChat $chat,
        private readonly TaskModels $models,
    ) {}

    /**
     * @param  list<string>  $tables  Markdown tables
     */
    public function describe(array $tables): string
    {
        return $this->chat->send(new ChatRequestData(
            model: $this->models->model(LlmTask::Tables),
            messages: [
                ChatMessageData::user(prompt('Llm/tables/describe', ['tables' => Arr::join($tables, "\n\n")])),
            ],
            tag: AiCallTag::TableDescription,
            temperature: 0.0,
            maxTokens: self::MAX_TOKENS,
        ))->content;
    }
}
