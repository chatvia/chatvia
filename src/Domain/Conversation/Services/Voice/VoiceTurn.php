<?php

declare(strict_types=1);

namespace Domain\Conversation\Services\Voice;

use Domain\Conversation\Services\ChatContext;

/**
 * One spoken turn of a voice call, from the moment the visitor stops
 * talking: what they said (once it is written down), what the agent said
 * back, and the turn's {@see ChatContext}, which collects the knowledge,
 * tools and usage like a typed turn's so the same recorders store it.
 *
 * The reply is saved only after the visitor's words, so the two are stored
 * in order even when the transcript arrives after the answer.
 */
final class VoiceTurn
{
    /** The visitor's words were stored (or the turn was refused). */
    public bool $userStored = false;

    /** The turn was refused (a limit, a guardrail): nothing more is stored for it. */
    public bool $refused = false;

    /** What the agent said in this turn, across tool rounds. */
    public string $spoken = '';

    /** How many times the agent called tools and went on in this turn. */
    public int $toolRounds = 0;

    /** When the reply was ready to save (waiting for the visitor's words), else null. */
    public ?float $replyReadyAt = null;

    public bool $replyStored = false;

    public function __construct(
        public readonly string $itemId,
        public readonly ChatContext $context,
    ) {}

    public function addUsage(int $inputTokens, int $outputTokens): void
    {
        $usage = $this->context->usage ?? ['prompt_tokens' => 0, 'completion_tokens' => 0];

        $this->context->usage = [
            'prompt_tokens' => $usage['prompt_tokens'] + $inputTokens,
            'completion_tokens' => $usage['completion_tokens'] + $outputTokens,
        ];
    }

    public function addSpoken(string $text): void
    {
        $text = trim($text);

        if ($text !== '') {
            $this->spoken = ltrim($this->spoken.' '.$text);
        }
    }
}
