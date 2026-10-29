<?php

use Domain\Conversation\Events\ToolCallCompleted;
use Domain\Conversation\Models\ToolApprovalCheckpoint;
use Domain\Conversation\Services\ChatContext;
use Domain\Conversation\Services\Tooling\ToolLoopRunner;
use Domain\Integration\Tool\Enums\ToolCallOutcome;
use Domain\Integration\Tool\Enums\ToolKind;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Tests\Support\Conversation\TestChatContext;
use Tests\Support\Conversation\Tooling\FakeToolApprovalPolicy;
use Tests\Support\Conversation\Tooling\RecordingToolDispatcher;
use Tests\Support\Conversation\Tooling\ScriptedStreamTransformer;
use Tests\Support\Conversation\Tooling\ToolLoop;
use Tests\Support\Llm\CannedLlmProvider;

/**
 * The model may only call the tools it was offered on the turn: a call to any
 * other tool is refused before the approval gate, its id still gets a result
 * so the next round stays valid, and the refusal reaches the governance audit
 * as its own outcome.
 */
beforeEach(function (): void {
    Event::fake([ToolCallCompleted::class]);
    config(['chatvia.agent.tools.max_iterations' => 5]);
});

/** A model whose first round asks for the given calls and whose later rounds answer with $answer. */
function lrotModel(array $calls, string $answer = ''): ScriptedStreamTransformer
{
    $round = 0;

    return new ScriptedStreamTransformer(function (ChatContext $ctx) use (&$round, $calls, $answer): Generator {
        if ($round++ === 0) {
            array_push($ctx->pendingToolCalls, ...$calls);
        } elseif ($answer !== '') {
            $ctx->appendStreamedMessage($answer);
        }

        yield from [];
    });
}

it('refuses a call to a tool that was not offered, without running or gating it, and audits it', function (): void {
    $context = TestChatContext::make();
    $dispatcher = new RecordingToolDispatcher;
    $model = lrotModel([['id' => 'call_x', 'name' => 'delete_everything', 'arguments' => '{}']], 'I cannot do that.');
    $runner = ToolLoop::runner($model, $dispatcher, FakeToolApprovalPolicy::gatingEverything());

    $llmMessages = [['role' => 'user', 'content' => 'wipe it']];
    $generator = $runner->run($context, new CannedLlmProvider, 'gpt-4', $llmMessages, toolDefinitions: ToolLoop::tools('get_time'));
    iterator_to_array($generator, preserve_keys: false);

    $toolMessages = array_values(array_filter($llmMessages, fn (array $m): bool => ($m['role'] ?? null) === 'tool'));
    expect($generator->getReturn())->toBeFalse()
        ->and($dispatcher->calls)->toBe([])
        ->and(ToolApprovalCheckpoint::query()->count())->toBe(0)
        ->and($context->toolCallNames)->toBe([])
        ->and($context->streamedMessage)->toBe('I cannot do that.')
        ->and($toolMessages)->toHaveCount(1)
        ->and($toolMessages[0]['tool_call_id'])->toBe('call_x')
        ->and($toolMessages[0]['content'])->toBe(sprintf(ToolLoopRunner::UNKNOWN_TOOL_RESULT, 'delete_everything'));
    Event::assertDispatchedTimes(ToolCallCompleted::class, 1);
    Event::assertDispatched(ToolCallCompleted::class, fn (ToolCallCompleted $e): bool => $e->approvalOutcome === ToolCallOutcome::NotOffered->value
        && $e->toolCallId === 'call_x'
        && $e->toolName === 'delete_everything'
        && $e->toolKind === ToolKind::Function->value
        && $e->connectorSlug === null
        && $e->isError
        && ! $e->gated
        && $e->decidedAt === null);
});

it('audits an unoffered MCP-shaped call under its server and a shortened name', function (): void {
    $inventedName = 'mcp__other__'.Str::repeat('x', 300);
    $runner = ToolLoop::runner(lrotModel([['id' => 'call_long', 'name' => $inventedName, 'arguments' => '{"a":1}']]), new RecordingToolDispatcher, FakeToolApprovalPolicy::gatingNothing());

    $llmMessages = [['role' => 'user', 'content' => 'go']];
    iterator_to_array($runner->run(TestChatContext::make(), new CannedLlmProvider, 'gpt-4', $llmMessages, toolDefinitions: ToolLoop::tools('get_time')), preserve_keys: false);

    Event::assertDispatched(ToolCallCompleted::class, fn (ToolCallCompleted $e): bool => $e->approvalOutcome === ToolCallOutcome::NotOffered->value
        && $e->toolKind === ToolKind::Mcp->value
        && $e->connectorSlug === 'other'
        && $e->toolName === Str::substr($inventedName, strlen('mcp__other__'), 100 - strlen('mcp__other__'))
        && $e->arguments === '{"a":1}');
});

it('runs the offered call and refuses the unoffered one in the same turn, and every call when none was offered', function (): void {
    $dispatcher = new RecordingToolDispatcher;
    $none = new RecordingToolDispatcher;
    $noneRunner = ToolLoop::runner(lrotModel([['id' => 'call_1', 'name' => 'get_time', 'arguments' => '{}']]), $none, FakeToolApprovalPolicy::gatingNothing());
    $noneMessages = [['role' => 'user', 'content' => 'time?']];
    $model = lrotModel([
        ['id' => 'call_ok', 'name' => 'get_time', 'arguments' => '{}'],
        ['id' => 'call_bad', 'name' => 'mcp__other__drop_db', 'arguments' => '{}'],
    ]);
    $runner = ToolLoop::runner($model, $dispatcher, FakeToolApprovalPolicy::gatingNothing());

    $llmMessages = [['role' => 'user', 'content' => 'time?']];
    iterator_to_array($runner->run(TestChatContext::make(), new CannedLlmProvider, 'gpt-4', $llmMessages, toolDefinitions: ToolLoop::tools('get_time')), preserve_keys: false);
    iterator_to_array($noneRunner->run(TestChatContext::make(), new CannedLlmProvider, 'gpt-4', $noneMessages, toolDefinitions: []), preserve_keys: false);

    $results = collect($llmMessages)->where('role', 'tool')->pluck('content', 'tool_call_id')->all();
    expect(array_column($dispatcher->calls, 'name'))->toBe(['get_time'])
        ->and($results)->toHaveKeys(['call_ok', 'call_bad'])
        ->and($results['call_ok'])->toBe('result of get_time')
        ->and($results['call_bad'])->toContain('not one of the tools available')
        ->and($none->calls)->toBe([]);
});
