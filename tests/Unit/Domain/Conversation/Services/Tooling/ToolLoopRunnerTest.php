<?php

use Domain\Conversation\Data\Messages\MessageStepData;
use Domain\Conversation\Enums\MessageStepKind;
use Domain\Conversation\Enums\ToolApprovalStatus;
use Domain\Conversation\Events\ToolCallCompleted;
use Domain\Conversation\Models\ToolApprovalCheckpoint;
use Domain\Conversation\Services\ChatContext;
use Domain\Conversation\Services\Streaming\Sse\EventType;
use Domain\Conversation\Services\Streaming\Sse\StreamEvent;
use Domain\Conversation\Services\Tooling\ToolLoopRunner;
use Domain\Conversation\Services\Turn\TurnContextFactory;
use Illuminate\Support\Facades\Event;
use Tests\Support\Conversation\TestChatContext;
use Tests\Support\Conversation\Tooling\FakeToolApprovalPolicy;
use Tests\Support\Conversation\Tooling\RecordingToolDispatcher;
use Tests\Support\Conversation\Tooling\ScriptedStreamTransformer;
use Tests\Support\Conversation\Tooling\ToolLoop;
use Tests\Support\Llm\CannedLlmProvider;

/**
 * The tool loop of a turn: it streams a completion, runs the tool calls the
 * model asks for (never streaming their result text to the client) and
 * streams again, pauses the whole turn (with a stored checkpoint) when a call
 * needs approval from a signed-in person, refuses it at once for an anonymous
 * visitor, and stops at the round cap with one last completion that may not
 * call tools. A broken approval gate could run a write tool nobody approved;
 * a broken cap could loop the model unbounded.
 */
beforeEach(function (): void {
    Event::fake([ToolCallCompleted::class]);
    config(['chatvia.agent.tools.max_iterations' => 5]);
});

it('runs an ungated call and continues to the answer, recording steps and never streaming the tool result text', function (): void {
    $context = TestChatContext::make();
    $dispatcher = new RecordingToolDispatcher('{"email":"jane@example.test","balance":1200}');
    $round = 0;
    $transformer = new ScriptedStreamTransformer(function (ChatContext $ctx) use (&$round): Generator {
        if ($round++ === 0) {
            $ctx->appendStreamedMessage('Let me check your account. ');
            $ctx->pendingToolCalls[] = ['id' => 'call_1', 'name' => 'get_customer', 'arguments' => '{}'];
        } else {
            $ctx->appendStreamedMessage('Your balance is 1200.');
        }
        yield StreamEvent::delta($ctx->assistantMessageId, 'x');
    });
    $runner = ToolLoop::runner($transformer, $dispatcher, FakeToolApprovalPolicy::gatingNothing());

    $llmMessages = [['role' => 'user', 'content' => 'who am I?']];
    $generator = $runner->run($context, new CannedLlmProvider, 'gpt-4', $llmMessages, toolDefinitions: ToolLoop::tools('get_customer'));
    $events = iterator_to_array($generator, preserve_keys: false);

    $toolMessages = array_values(array_filter($llmMessages, fn (array $m): bool => ($m['role'] ?? null) === 'tool'));
    $end = collect($events)->first(fn (StreamEvent $event): bool => $event->type === EventType::Tool && $event->data['phase'] === 'end');
    expect($generator->getReturn())->toBeFalse()
        ->and(array_column($dispatcher->calls, 'name'))->toBe(['get_customer'])
        ->and($context->toolCallNames)->toBe(['get_customer'])
        ->and(array_column($toolMessages, 'content'))->toBe(['{"email":"jane@example.test","balance":1200}'])
        ->and($context->streamedMessage)->toBe('Your balance is 1200.')
        ->and(array_map(fn (MessageStepData $step): array => [$step->kind, $step->text ?? $step->label], $context->steps))->toBe([
            [MessageStepKind::Text, 'Let me check your account.'],
            [MessageStepKind::Tool, 'get_customer'],
        ])
        ->and($end->data)->not->toHaveKey('result')
        ->and(json_encode(array_map(fn (StreamEvent $event): array => $event->data, $events)))->not->toContain('jane@example.test');
});

it('pauses the turn with a checkpoint keeping its steps when a call needs approval, and refuses it at once for an anonymous visitor', function (): void {
    $gatedModel = function (): ScriptedStreamTransformer {
        $round = 0;

        return new ScriptedStreamTransformer(function (ChatContext $ctx) use (&$round): Generator {
            if ($round++ === 0) {
                $ctx->appendStreamedMessage('I will delete your account.');
                $ctx->pendingToolCalls[] = ['id' => 'call_1', 'name' => 'delete_user', 'arguments' => '{}'];
            }
            yield StreamEvent::delta($ctx->assistantMessageId, 'x');
        });
    };
    $signedIn = new RecordingToolDispatcher;
    $anonymous = new RecordingToolDispatcher;
    $signedInMessages = [['role' => 'user', 'content' => 'delete me']];
    $anonymousMessages = [['role' => 'user', 'content' => 'delete me']];

    $paused = ToolLoop::runner($gatedModel(), $signedIn, FakeToolApprovalPolicy::gatingEverything())
        ->run(TestChatContext::make(chatterId: 1), new CannedLlmProvider, 'gpt-4', $signedInMessages, toolDefinitions: ToolLoop::tools('delete_user'));
    $pausedEvents = iterator_to_array($paused, preserve_keys: false);
    $checkpoint = ToolApprovalCheckpoint::query()->sole();
    $refused = ToolLoop::runner($gatedModel(), $anonymous, FakeToolApprovalPolicy::gatingEverything())
        ->run(TestChatContext::make(), new CannedLlmProvider, 'gpt-4', $anonymousMessages, toolDefinitions: ToolLoop::tools('delete_user'));
    $refusedEvents = iterator_to_array($refused, preserve_keys: false);

    $resumed = app(TurnContextFactory::class)->forResume($checkpoint);
    $anonymousToolMessages = array_values(array_filter($anonymousMessages, fn (array $m): bool => ($m['role'] ?? null) === 'tool'));
    expect($paused->getReturn())->toBeTrue()
        ->and($signedIn->calls)->toBe([])
        ->and($checkpoint->status)->toBe(ToolApprovalStatus::Pending)
        ->and(array_column($checkpoint->pending_calls, 'name'))->toBe(['delete_user'])
        ->and(array_last($pausedEvents)->data['reason'])->toBe('awaiting_approval')
        ->and(array_map(fn (MessageStepData $step): ?string => $step->text, $resumed->steps))->toBe(['I will delete your account.'])
        ->and($refused->getReturn())->toBeFalse()
        ->and($anonymous->calls)->toBe([])
        ->and(ToolApprovalCheckpoint::query()->count())->toBe(1)
        ->and(array_column($anonymousToolMessages, 'content'))->toBe([ToolLoopRunner::SIGN_IN_REQUIRED_RESULT])
        ->and(collect($refusedEvents)->where('type', EventType::Tool)->pluck('data.phase')->all())->not->toContain('awaiting_approval');
});

it('stops at the configured round cap and then answers without tools, also on a resumed turn with no rounds left', function (): void {
    config(['chatvia.agent.tools.max_iterations' => 2]);
    $context = TestChatContext::make();
    $dispatcher = new RecordingToolDispatcher;
    $provider = new CannedLlmProvider;
    $round = 0;
    $transformer = new ScriptedStreamTransformer(function (ChatContext $ctx) use (&$round): Generator {
        $ctx->pendingToolCalls[] = ['id' => 'call_'.$round, 'name' => 'loop_tool', 'arguments' => '{}'];
        $ctx->streamedMessage .= 'answer '.$round++;
        yield StreamEvent::delta($ctx->assistantMessageId, 'x');
    });
    $resumedProvider = new CannedLlmProvider;
    $resumedDispatcher = new RecordingToolDispatcher;

    $llmMessages = [['role' => 'user', 'content' => 'go']];
    $generator = ToolLoop::runner($transformer, $dispatcher, FakeToolApprovalPolicy::gatingNothing())
        ->run($context, $provider, 'gpt-4', $llmMessages, toolDefinitions: ToolLoop::tools('loop_tool'));
    iterator_to_array($generator, preserve_keys: false);
    $resumedMessages = [['role' => 'user', 'content' => 'go']];
    iterator_to_array(ToolLoop::runner(ScriptedStreamTransformer::silent(), $resumedDispatcher, FakeToolApprovalPolicy::gatingNothing())
        ->run(TestChatContext::make(), $resumedProvider, 'gpt-4', $resumedMessages, toolDefinitions: ToolLoop::tools('loop_tool'), startIteration: 2), preserve_keys: false);

    expect($generator->getReturn())->toBeFalse()
        ->and($dispatcher->calls)->toHaveCount(2)
        ->and(array_column($provider->streamSettings, 'tool_choice'))->toBe(['auto', 'auto', 'none'])
        ->and($provider->streamSettings[2]['tools'])->toBe(ToolLoop::tools('loop_tool'))
        ->and($context->pendingToolCalls)->toBe([])
        ->and($context->streamedMessage)->toBe('answer 2')
        ->and(array_column($resumedProvider->streamSettings, 'tool_choice'))->toBe(['none'])
        ->and($resumedDispatcher->calls)->toBe([]);
});
