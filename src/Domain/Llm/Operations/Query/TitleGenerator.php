<?php

declare(strict_types=1);

namespace Domain\Llm\Operations\Query;

use Domain\Llm\Data\Chat\ChatMessageData;
use Domain\Llm\Data\Chat\ChatRequestData;
use Domain\Llm\Enums\AiCallTag;
use Domain\Llm\Enums\LlmTask;
use Domain\Llm\Services\LlmChat;
use Domain\Llm\Services\TaskModels;
use Illuminate\Support\Str;

/**
 * Writes a short title for a conversation from its first messages, with the
 * Titles model ({@see LlmTask::Titles}). The reply is cleaned to one short
 * line; it is empty when the model wrote nothing.
 */
class TitleGenerator
{
    /**
     * Room for the reply. The title is a few words, but a reasoning model
     * (gpt-oss, gpt-5…) spends tokens thinking first: with a tight cap it
     * stops before writing anything and the title comes back empty.
     */
    private const int MAX_TOKENS = 400;

    /** Longest title kept, in characters. */
    private const int MAX_TITLE_LENGTH = 80;

    public function __construct(
        private readonly LlmChat $chat,
        private readonly TaskModels $models,
    ) {}

    public function generate(string $messages): string
    {
        return self::clean($this->chat->send(new ChatRequestData(
            model: $this->models->model(LlmTask::Titles),
            messages: [
                ChatMessageData::system(prompt('conversation/title')),
                ChatMessageData::user("The text is: {$messages}"),
            ],
            tag: AiCallTag::ConversationTitle,
            maxTokens: self::MAX_TOKENS,
        ))->content);
    }

    /**
     * The model's reply as a title: its first non-empty line, without a
     * "Title:" label, markdown marks, quotes or a closing full stop.
     */
    public static function clean(string $reply): string
    {
        $line = Str::of($reply)
            ->split('/\R/u')
            ->map(static fn (string $line): string => Str::trim($line))
            ->first(filled(...)) ?? '';

        return Str::of($line)
            ->replaceMatches('/^(#+\s*|[*_]+\s*)?(title\s*:\s*)?/iu', '')
            ->trim(" \t*_#\"'`“”‘’.")
            ->limit(self::MAX_TITLE_LENGTH, '')
            ->toString();
    }
}
