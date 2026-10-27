<?php

declare(strict_types=1);

namespace Tests\Support\Conversation;

use Closure;
use Domain\Conversation\Services\ChatContext;
use Domain\Conversation\Services\Streaming\Sse\StreamEvent;
use Domain\Conversation\Services\Tooling\ToolLoopRunner;
use Domain\Llm\Contracts\ChatCompletionProvider;
use Generator;
use LogicException;

/**
 * A tool loop whose run() is a script, so a test of a streaming source
 * decides what the loop streams and whether it pauses for approval. The
 * script gets the turn's context and its LLM messages, yields stream events
 * and returns true when the turn paused. The model and start round of every
 * run are kept, and so are the decisions a resumed turn applies.
 */
final class ScriptedToolLoopRunner extends ToolLoopRunner
{
    /** @var list<array{model: string, start_iteration: int}> */
    public array $runs = [];

    /** @var list<array<string, mixed>> the decisions of every applyPendingCalls() call */
    public array $appliedDecisions = [];

    /**
     * @param  Closure(ChatContext, array<int, array<string, mixed>>): Generator  $script
     */
    public function __construct(private readonly Closure $script)
    {
        // No parent constructor: the script stands in for every collaborator.
    }

    /** A loop that streams this answer and finishes the turn. */
    public static function answering(string $answer): self
    {
        return new self(static function (ChatContext $context) use ($answer): Generator {
            $context->appendStreamedMessage($answer);
            yield StreamEvent::delta($context->assistantMessageId, $answer);

            return false;
        });
    }

    /** A loop that must never run, for a turn answered before it. */
    public static function never(): self
    {
        return new self(static function (): Generator {
            throw new LogicException('The tool loop must not run on this turn.');
        });
    }

    public function run(
        ChatContext $context,
        ChatCompletionProvider $provider,
        string $modelName,
        array &$llmMessages,
        array $toolDefinitions,
        array $extraSettings = [],
        int $startIteration = 0,
        bool $captureToolCallsOnly = false,
    ): Generator {
        $this->runs[] = ['model' => $modelName, 'start_iteration' => $startIteration];

        return yield from ($this->script)($context, $llmMessages);
    }

    public function applyPendingCalls(ChatContext $context, array &$llmMessages, array $pendingCalls, array $decisions): Generator
    {
        $this->appliedDecisions[] = $decisions;

        yield from [];
    }
}
