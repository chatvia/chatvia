<?php

use Domain\Conversation\Data\Messages\MessageStepData;
use Domain\Conversation\Enums\MessageStepKind;
use Domain\Conversation\Enums\ToolApprovalStatus;
use Domain\Conversation\Events\ToolCallCompleted;
use Domain\Conversation\Models\ToolApprovalCheckpoint;
use Domain\Conversation\Services\ChatContext;
use Domain\Conversation\Services\Streaming\Exceptions\TurnStopped;
use Domain\Conversation\Services\Streaming\Sse\EventType;
use Domain\Conversation\Services\Streaming\Sse\StreamEvent;
use Domain\Conversation\Services\Tooling\ToolLoopRunner;
use Domain\Conversation\Services\Turn\TurnContextFactory;
use Domain\Conversation\Services\Turn\TurnStops;
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
    // Signed in: only a chatter or staff may approve.
    $context = TestChatContext::make(chatterId: 1);
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

it('refuses a gated call at once for an anonymous visitor, without a checkpoint', function (): void {
    $context = TestChatContext::make();
    $dispatcher = new RecordingToolDispatcher;
    $round = 0;
    $transformer = new ScriptedStreamTransformer(function (ChatContext $ctx) use (&$round): Generator {
        if ($round++ === 0) {
            $ctx->pendingToolCalls[] = ['id' => 'call_1', 'name' => 'delete_user', 'arguments' => '{}'];
        }
        yield StreamEvent::delta($ctx->assistantMessageId, 'x');
    });
    $runner = ToolLoop::runner($transformer, $dispatcher, FakeToolApprovalPolicy::gatingEverything());

    $llmMessages = [['role' => 'user', 'content' => 'delete me']];
    $generator = $runner->run($context, new CannedLlmProvider, 'gpt-4', $llmMessages, toolDefinitions: ToolLoop::tools('delete_user'));
    $events = iterator_to_array($generator, preserve_keys: false);

    $toolMessages = array_values(array_filter($llmMessages, fn (array $m): bool => ($m['role'] ?? null) === 'tool'));
    expect($generator->getReturn())->toBeFalse()
        ->and($dispatcher->calls)->toBe([])
        ->and(ToolApprovalCheckpoint::query()->count())->toBe(0)
        ->and(array_column($toolMessages, 'content'))->toBe([ToolLoopRunner::SIGN_IN_REQUIRED_RESULT])
        ->and(collect($events)->where('type', EventType::Tool)->pluck('data.phase')->all())->not->toContain('awaiting_approval');
});

it('never streams the tool result text to the client, only status and artifacts', function (): void {
    $context = TestChatContext::make();
    $dispatcher = new RecordingToolDispatcher('{"email":"jane@example.test","balance":1200}');
    $round = 0;
    $transformer = new ScriptedStreamTransformer(function (ChatContext $ctx) use (&$round): Generator {
        if ($round++ === 0) {
            $ctx->pendingToolCalls[] = ['id' => 'call_1', 'name' => 'get_customer', 'arguments' => '{}'];
        }
        yield StreamEvent::delta($ctx->assistantMessageId, 'x');
    });
    $runner = ToolLoop::runner($transformer, $dispatcher, FakeToolApprovalPolicy::gatingNothing());

    $llmMessages = [['role' => 'user', 'content' => 'who am I?']];
    $events = iterator_to_array($runner->run($context, new CannedLlmProvider, 'gpt-4', $llmMessages, toolDefinitions: ToolLoop::tools('get_customer')), preserve_keys: false);

    $end = collect($events)->first(fn (StreamEvent $event): bool => $event->type === EventType::Tool && $event->data['phase'] === 'end');
    expect($end->data)->not->toHaveKey('result')
        ->and(json_encode(array_map(fn (StreamEvent $event): array => $event->data, $events)))->not->toContain('jane@example.test');
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

it('records what the model wrote before a tool call and the call itself as steps, not as the answer', function (): void {
    $context = TestChatContext::make();
    $round = 0;
    $transformer = new ScriptedStreamTransformer(function (ChatContext $ctx) use (&$round): Generator {
        if ($round++ === 0) {
            $ctx->appendStreamedMessage('Let me check the time. ');
            $ctx->pendingToolCalls[] = ['id' => 'call_1', 'name' => 'get_time', 'arguments' => '{}'];
        } else {
            $ctx->appendStreamedMessage('It is noon.');
        }
        yield StreamEvent::delta($ctx->assistantMessageId, 'x');
    });
    $runner = ToolLoop::runner($transformer, new RecordingToolDispatcher, FakeToolApprovalPolicy::gatingNothing());

    $llmMessages = [['role' => 'user', 'content' => 'time?']];
    iterator_to_array($runner->run($context, new CannedLlmProvider, 'gpt-4', $llmMessages, toolDefinitions: ToolLoop::tools('get_time')), preserve_keys: false);

    expect(array_map(fn (MessageStepData $step): array => [$step->kind, $step->text ?? $step->label], $context->steps))->toBe([
        [MessageStepKind::Text, 'Let me check the time.'],
        [MessageStepKind::Tool, 'get_time'],
    ])->and($context->streamedMessage)->toBe('It is noon.');
});

it('keeps the steps before a pause with the checkpoint, so the resumed turn saves the whole chain', function (): void {
    $context = TestChatContext::make(chatterId: 1);
    $transformer = new ScriptedStreamTransformer(function (ChatContext $ctx): Generator {
        $ctx->appendStreamedMessage('I will delete your account.');
        $ctx->pendingToolCalls[] = ['id' => 'call_1', 'name' => 'delete_user', 'arguments' => '{}'];
        yield StreamEvent::delta($ctx->assistantMessageId, 'x');
    });
    $runner = ToolLoop::runner($transformer, new RecordingToolDispatcher, FakeToolApprovalPolicy::gatingEverything());

    $llmMessages = [['role' => 'user', 'content' => 'delete me']];
    iterator_to_array($runner->run($context, new CannedLlmProvider, 'gpt-4', $llmMessages, toolDefinitions: ToolLoop::tools('delete_user')), preserve_keys: false);

    $resumed = app(TurnContextFactory::class)->forResume(ToolApprovalCheckpoint::query()->sole());

    expect(array_map(fn (MessageStepData $step): ?string => $step->text, $resumed->steps))->toBe(['I will delete your account.']);
});

it('starts no tool once the turn was stopped', function (): void {
    $context = TestChatContext::make(requestId: 'chat.a.s.c.r1');
    $dispatcher = new RecordingToolDispatcher;
    $transformer = new ScriptedStreamTransformer(function (ChatContext $ctx): Generator {
        $ctx->pendingToolCalls[] = ['id' => 'call_1', 'name' => 'get_time', 'arguments' => '{}'];
        // The visitor presses Stop while the model asks for the tool.
        app(TurnStops::class)->request('chat.a.s.c.r1');
        yield StreamEvent::delta($ctx->assistantMessageId, 'x');
    });
    $runner = ToolLoop::runner($transformer, $dispatcher, FakeToolApprovalPolicy::gatingNothing());
    $llmMessages = [['role' => 'user', 'content' => 'time?']];

    expect(fn () => iterator_to_array($runner->run($context, new CannedLlmProvider, 'gpt-4', $llmMessages, toolDefinitions: ToolLoop::tools('get_time')), false))
        ->toThrow(TurnStopped::class)
        ->and($dispatcher->calls)->toBe([]);
});

it('asks the model nothing more once the turn was stopped', function (): void {
    $context = TestChatContext::make(requestId: 'chat.a.s.c.r1');
    app(TurnStops::class)->request('chat.a.s.c.r1');
    $rounds = 0;
    $transformer = new ScriptedStreamTransformer(function (ChatContext $ctx) use (&$rounds): Generator {
        $rounds++;
        yield StreamEvent::delta($ctx->assistantMessageId, 'x');
    });
    $runner = ToolLoop::runner($transformer, new RecordingToolDispatcher, FakeToolApprovalPolicy::gatingNothing());
    $llmMessages = [['role' => 'user', 'content' => 'Hi']];

    expect(fn () => iterator_to_array($runner->run($context, new CannedLlmProvider, 'gpt-4', $llmMessages, toolDefinitions: []), false))
        ->toThrow(TurnStopped::class)
        ->and($rounds)->toBe(0);
});
