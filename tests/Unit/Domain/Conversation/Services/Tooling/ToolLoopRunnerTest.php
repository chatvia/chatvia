<?php

use Domain\Conversation\Enums\ToolApprovalStatus;
use Domain\Conversation\Events\ToolCallCompleted;
use Domain\Conversation\Models\ToolApprovalCheckpoint;
use Domain\Conversation\Services\ChatContext;
use Domain\Conversation\Services\Streaming\Sse\EventType;
use Domain\Conversation\Services\Streaming\Sse\StreamEvent;
use Illuminate\Support\Facades\Event;
use Tests\Support\Conversation\TestChatContext;
use Tests\Support\Conversation\Tooling\FakeToolApprovalPolicy;
use Tests\Support\Conversation\Tooling\RecordingToolDispatcher;
use Tests\Support\Conversation\Tooling\ScriptedStreamTransformer;
use Tests\Support\Conversation\Tooling\ToolLoop;
use Tests\Support\Llm\CannedLlmProvider;

/**
 * The tool loop of a turn: it streams a completion, runs the tool calls the
 * model asks for and streams again, pauses the whole turn (with a stored
 * checkpoint) when a call needs approval, and stops at the round cap with one
 * last completion that may not call tools. A broken approval gate could run a
 * write tool nobody approved; a broken cap could loop the model unbounded.
 */
beforeEach(function (): void {
    // The runner emits ToolCallCompleted for the governance audit. Faking it
    // lets the tests check the event without its queued listener writing rows.
    Event::fake([ToolCallCompleted::class]);
    config(['chatvia.agent.tools.max_iterations' => 5]);
});

it('streams the final answer and does not pause when the model calls no tool', function (): void {
    $context = TestChatContext::make();
    $dispatcher = new RecordingToolDispatcher;
    $transformer = new ScriptedStreamTransformer(function (ChatContext $ctx): Generator {
        $ctx->appendStreamedMessage('Hello, world.');
        yield StreamEvent::delta($ctx->assistantMessageId, 'Hello, world.');
    });
    $runner = ToolLoop::runner($transformer, $dispatcher, FakeToolApprovalPolicy::gatingNothing());

    $llmMessages = [['role' => 'user', 'content' => 'Hi']];
    $generator = $runner->run($context, new CannedLlmProvider, 'gpt-4', $llmMessages, toolDefinitions: []);
    $events = iterator_to_array($generator, preserve_keys: false);

    $deltas = array_values(array_filter($events, fn (StreamEvent $e): bool => $e->type === EventType::Delta));
    expect($generator->getReturn())->toBeFalse()
        ->and($dispatcher->calls)->toBe([])
        ->and(array_column(array_column($deltas, 'data'), 'text'))->toBe(['Hello, world.'])
        ->and($context->streamedMessage)->toBe('Hello, world.');
});

it('runs an ungated tool call and continues to a final answer', function (): void {
    $context = TestChatContext::make();
    $dispatcher = new RecordingToolDispatcher;
    $turn = 0;
    $transformer = new ScriptedStreamTransformer(function (ChatContext $ctx) use (&$turn): Generator {
        if ($turn++ === 0) {
            // First model round requests a tool…
            $ctx->pendingToolCalls[] = ['id' => 'call_1', 'name' => 'get_time', 'arguments' => '{}'];
        } else {
            // …second round answers with the tool result in hand.
            $ctx->appendStreamedMessage('It is noon.');
        }
        yield StreamEvent::delta($ctx->assistantMessageId, 'x');
    });
    $runner = ToolLoop::runner($transformer, $dispatcher, FakeToolApprovalPolicy::gatingNothing());

    $llmMessages = [['role' => 'user', 'content' => 'time?']];
    $generator = $runner->run($context, new CannedLlmProvider, 'gpt-4', $llmMessages, toolDefinitions: ToolLoop::tools('get_time'));
    iterator_to_array($generator, preserve_keys: false);

    // The tool result was given to the model for its next round.
    $toolMessages = array_values(array_filter($llmMessages, fn (array $m): bool => ($m['role'] ?? null) === 'tool'));
    expect($generator->getReturn())->toBeFalse()
        ->and(array_column($dispatcher->calls, 'name'))->toBe(['get_time'])
        ->and($context->toolCallNames)->toBe(['get_time'])
        ->and(array_column($toolMessages, 'content'))->toBe(['result of get_time'])
        ->and($context->streamedMessage)->toBe('It is noon.');
});

it('pauses the whole turn and stores a pending checkpoint when a tool call needs approval', function (): void {
    $context = TestChatContext::make();
    $dispatcher = new RecordingToolDispatcher;
    $transformer = new ScriptedStreamTransformer(function (ChatContext $ctx): Generator {
        $ctx->pendingToolCalls[] = ['id' => 'call_1', 'name' => 'delete_user', 'arguments' => '{}'];
        yield StreamEvent::delta($ctx->assistantMessageId, 'x');
    });
    $runner = ToolLoop::runner($transformer, $dispatcher, FakeToolApprovalPolicy::gatingEverything());

    $llmMessages = [['role' => 'user', 'content' => 'delete me']];
    $generator = $runner->run($context, new CannedLlmProvider, 'gpt-4', $llmMessages, toolDefinitions: ToolLoop::tools('delete_user'));
    $events = iterator_to_array($generator, preserve_keys: false);

    $checkpoint = ToolApprovalCheckpoint::query()->sole();
    expect($generator->getReturn())->toBeTrue()
        ->and($dispatcher->calls)->toBe([])
        ->and($checkpoint->status)->toBe(ToolApprovalStatus::Pending)
        ->and(array_column($checkpoint->pending_calls, 'name'))->toBe(['delete_user'])
        // The stream ends with an awaiting_approval `done`.
        ->and(array_last($events)->data['reason'])->toBe('awaiting_approval');
});

it('stops at the configured round cap and then answers without tools', function (): void {
    config(['chatvia.agent.tools.max_iterations' => 2]);
    $context = TestChatContext::make();
    $dispatcher = new RecordingToolDispatcher;
    $provider = new CannedLlmProvider;
    // Every model round asks for another ungated tool; only the cap stops it.
    $round = 0;
    $transformer = new ScriptedStreamTransformer(function (ChatContext $ctx) use (&$round): Generator {
        $ctx->pendingToolCalls[] = ['id' => 'call_'.$round, 'name' => 'loop_tool', 'arguments' => '{}'];
        $ctx->streamedMessage .= 'answer '.$round++;
        yield StreamEvent::delta($ctx->assistantMessageId, 'x');
    });
    $runner = ToolLoop::runner($transformer, $dispatcher, FakeToolApprovalPolicy::gatingNothing());

    $llmMessages = [['role' => 'user', 'content' => 'go']];
    $generator = $runner->run($context, $provider, 'gpt-4', $llmMessages, toolDefinitions: ToolLoop::tools('loop_tool'));
    iterator_to_array($generator, preserve_keys: false);

    // Two tool rounds, then one more completion that may not call tools. The
    // call the model still asks for on it is dropped, never run.
    expect($generator->getReturn())->toBeFalse()
        ->and($dispatcher->calls)->toHaveCount(2)
        ->and(array_column($provider->streamSettings, 'tool_choice'))->toBe(['auto', 'auto', 'none'])
        ->and($provider->streamSettings[2]['tools'])->toBe(ToolLoop::tools('loop_tool'))
        ->and($context->pendingToolCalls)->toBe([])
        ->and($context->streamedMessage)->toBe('answer 2');
});

it('answers without tools at once when a resumed turn has no rounds left', function (): void {
    config(['chatvia.agent.tools.max_iterations' => 2]);
    $context = TestChatContext::make();
    $dispatcher = new RecordingToolDispatcher;
    $provider = new CannedLlmProvider;
    $transformer = new ScriptedStreamTransformer(function (ChatContext $ctx): Generator {
        $ctx->streamedMessage .= 'done';
        yield StreamEvent::delta($ctx->assistantMessageId, 'done');
    });
    $runner = ToolLoop::runner($transformer, $dispatcher, FakeToolApprovalPolicy::gatingNothing());

    $llmMessages = [['role' => 'user', 'content' => 'go']];
    iterator_to_array($runner->run($context, $provider, 'gpt-4', $llmMessages, toolDefinitions: ToolLoop::tools('loop_tool'), startIteration: 2), preserve_keys: false);

    expect(array_column($provider->streamSettings, 'tool_choice'))->toBe(['none'])
        ->and($dispatcher->calls)->toBe([])
        ->and($context->streamedMessage)->toBe('done');
});

it('runs at least one round when the cap is set below one', function (): void {
    config(['chatvia.agent.tools.max_iterations' => 0]);
    $provider = new CannedLlmProvider;
    $runner = ToolLoop::runner(ScriptedStreamTransformer::silent(), new RecordingToolDispatcher, FakeToolApprovalPolicy::gatingNothing());

    $llmMessages = [['role' => 'user', 'content' => 'go']];
    iterator_to_array($runner->run(TestChatContext::make(), $provider, 'gpt-4', $llmMessages, toolDefinitions: ToolLoop::tools('loop_tool')), preserve_keys: false);

    expect(array_column($provider->streamSettings, 'tool_choice'))->toBe(['auto']);
});
