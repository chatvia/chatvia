<?php

namespace Domain\Chat\Services\Tooling;

/**
 * What the tool layer hands back to the chat stream after a tool call:
 *
 *   - `text`      → the string the LLM sees on its next turn (role: 'tool').
 *                   Always present; even errors are stringified so the model
 *                   can recover.
 *
 *   - `artifacts` → structured side-channel payloads the **UI** consumes
 *                   directly (the LLM does not see these). Each artifact is a
 *                   self-describing block:
 *                       ['kind' => 'echarts_option', 'data' => [...]]
 *                   The chat renderer switches on `kind`.
 *
 * The split exists because some tools (charts, maps, tables) want to render
 * something richer than text in the chat bubble while still feeding the LLM
 * a useful summary it can reason about. Text-only tools just leave the
 * artifacts array empty.
 */
final readonly class ToolDispatchResult
{
    /**
     * @param  array<int, array<string, mixed>>  $artifacts
     */
    public function __construct(
        public string $text,
        public array $artifacts = [],
    ) {}

    public static function text(string $text): self
    {
        return new self($text);
    }

    /**
     * @param  array<int, array<string, mixed>>  $artifacts
     */
    public static function withArtifacts(string $text, array $artifacts): self
    {
        return new self($text, $artifacts);
    }
}
