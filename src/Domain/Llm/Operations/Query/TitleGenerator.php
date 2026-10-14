<?php

declare(strict_types=1);

namespace Domain\Llm\Operations\Query;

use Domain\Llm\Data\Chat\ChatMessageData;
use Domain\Llm\Data\Chat\ChatRequestData;
use Domain\Llm\Enums\LlmRole;
use Domain\Llm\Services\LlmChat;
use Domain\Llm\Services\ModelRoles;
use Illuminate\Support\Str;

/**
 * Writes a short title for a conversation from its first messages, with the
 * Fast model ({@see LlmRole}). The reply is cleaned to one short line; it is
 * empty when the model wrote nothing.
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
        private readonly ModelRoles $models,
    ) {}

    public function generate(string $messages): string
    {
        return self::clean($this->chat->send(new ChatRequestData(
            model: $this->models->model(LlmRole::Fast),
            messages: [
                ChatMessageData::system(prompt('conversation/title')),
                ChatMessageData::user('The text is: '.$messages),
            ],
            maxTokens: self::MAX_TOKENS,
        ))->content);
    }

    /**
     * The model's reply as a title: its first non-empty line, without a
     * "Title:" label, markdown marks, quotes or a closing full stop.
     */
    public static function clean(string $reply): string
    {
        $line = collect(preg_split('/\R/u', $reply) ?: [])
            ->map(static fn (string $line): string => trim($line))
            ->first(filled(...)) ?? '';

        $line = (string) preg_replace('/^(#+\s*|[*_]+\s*)?(title\s*:\s*)?/iu', '', $line);
        $line = trim($line, " \t*_#\"'`“”‘’.");

        return Str::limit($line, self::MAX_TITLE_LENGTH, '');
    }
}
