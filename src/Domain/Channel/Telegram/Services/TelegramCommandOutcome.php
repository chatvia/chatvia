<?php

namespace Domain\Channel\Telegram\Services;

/**
 * What a matched Telegram command resolves to: either a canned **message** sent
 * verbatim (no LLM, nothing counted) or a **prompt** run through the agent pipeline
 * like a normal turn (RAG + tools + LLM, billed). The Action branches on
 * {@see $runThroughAgent} to decide which path to take.
 */
final readonly class TelegramCommandOutcome
{
    private function __construct(
        public string $text,
        public bool $runThroughAgent,
    ) {}

    /** A canned reply to send as-is. */
    public static function message(string $text): self
    {
        return new self($text, false);
    }

    /** A prompt to feed the agent pipeline; its generated answer is the reply. */
    public static function prompt(string $prompt): self
    {
        return new self($prompt, true);
    }
}
