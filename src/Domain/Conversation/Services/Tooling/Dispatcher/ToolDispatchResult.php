<?php

namespace Domain\Conversation\Services\Tooling\Dispatcher;

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
        public bool $isError = false,
    ) {}

    public static function text(string $text): self
    {
        return new self($text);
    }

    /**
     * A result carrying renderable artifacts (charts, forms, …).
     *
     * The chat renderer drops each artifact into the reply at the matching
     * `![...]` marker the model writes (paired by order), and only stacks
     * artifacts above the text when no marker is present. So we append a short
     * note — role: tool, never shown to the user — telling the model to emit
     * one own-line marker per artifact, in order. That nudge is what makes the
     * visual land where the model intends instead of always on top; if the
     * model ignores it, the renderer's stack-above fallback still shows every
     * artifact.
     *
     * @param  array<int, array<string, mixed>>  $artifacts
     */
    public static function withArtifacts(string $text, array $artifacts): self
    {
        if ($artifacts === []) {
            return new self($text, []);
        }

        return new self(trim($text)."\n\n".self::placementNote($artifacts), $artifacts);
    }

    /**
     * @param  array<int, array<string, mixed>>  $artifacts
     */
    private static function placementNote(array $artifacts): string
    {
        $markers = array_map(
            static fn (array $artifact): string => ($artifact['kind'] ?? null) === 'form' ? '![form]' : '![chart]',
            $artifacts,
        );

        $sequence = implode(', then ', $markers);
        $count = count($markers);

        return '(Display note — the user does not see this message. The visual has been rendered for the user. '
            ."In your reply, write {$sequence} on its own line at the exact point where it should appear, then continue. "
            .($count > 1 ? "Place the {$count} markers in this order, one per visual." : 'Write the marker exactly once.')
            .')';
    }

    /**
     * A recoverable tool error (the LLM still sees the text). Flagged so the
     * governance audit can record the call as failed.
     */
    public static function error(string $text): self
    {
        return new self($text, [], true);
    }
}
