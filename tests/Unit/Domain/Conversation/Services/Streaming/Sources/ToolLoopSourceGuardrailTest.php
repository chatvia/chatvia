<?php

use Domain\Conversation\Data\Tooling\PendingToolCallData;
use Domain\Conversation\Services\ChatContext;
use Domain\Conversation\Services\Streaming\Exceptions\ClientDisconnected;
use Domain\Conversation\Services\Streaming\Requests\RequestDedupeStore;
use Domain\Conversation\Services\Streaming\Sources\AttachmentSource;
use Domain\Conversation\Services\Streaming\Sources\LlmSource;
use Domain\Conversation\Services\Streaming\Sse\EventType;
use Domain\Conversation\Services\Streaming\Sse\StreamEvent;
use Domain\Conversation\Services\Tooling\ToolLoopRunner;
use Domain\Conversation\Services\Turn\CitationMarkers;
use Domain\Integration\Tool\Enums\ToolKind;
use Domain\Llm\Services\ChatFileModel;
use Domain\Llm\Services\LlmProviders;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Tests\Support\Conversation\NoToolSelector;
use Tests\Support\Conversation\RecordingGuardrailRecorder;
use Tests\Support\Conversation\RecordingReplyRecorder;
use Tests\Support\Conversation\RedactingOutputGuard;
use Tests\Support\Conversation\ScriptedToolLoopRunner;
use Tests\Support\Conversation\TestChatContext;

/**
 * Every source that runs the tool loop shares one finish step, so the output
 * guardrails (PII redaction / toxicity replacement) run before ANY answer is
 * saved or replayed — chats with attached files included, and also the partial answer saved
 * after the visitor disconnects. What the model wrote before its tool calls
 * is saved as steps, so it passes the same guards. The runner and the guard are faked; the
 * assertions are on what the source saves and streams.
 */
/**
 * Build the named source with the shared fakes and return its event stream.
 *
 * @return array{0: Generator<int, StreamEvent>, 1: RecordingReplyRecorder, 2: RecordingGuardrailRecorder}
 */
function tlsStream(string $source, ChatContext $context, ToolLoopRunner $runner): array
{
    $replyRecorder = new RecordingReplyRecorder;
    $recorder = new RecordingGuardrailRecorder;

    $shared = [$replyRecorder, new RequestDedupeStore, new NoToolSelector, $runner, new RedactingOutputGuard(['a@b.com' => '[EMAIL]']), $recorder, app(CitationMarkers::class)];

    $events = match ($source) {
        'files' => new AttachmentSource(app(ChatFileModel::class), app(LlmProviders::class), ...$shared)->events($context, [['role' => 'user', 'content' => 'Hello']], collect()),
        default => new LlmSource(app(LlmProviders::class), ...$shared)->events($context, [['role' => 'user', 'content' => 'Hello']]),
    };

    return [$events, $replyRecorder, $recorder];
}

beforeEach(function (): void {
    Cache::flush();
    Log::spy();
    // The attachment source answers with the model for chat files.
    useChatFiles();
});

dataset('tool loop sources', ['llm', 'files']);

it('redacts the answer with the output guardrails before saving and replaying it', function (string $source): void {
    $context = TestChatContext::make();
    $runner = new ScriptedToolLoopRunner(function (ChatContext $ctx): Generator {
        $ctx->appendStreamedMessage('Mail me at a@b.com');
        yield StreamEvent::delta($ctx->assistantMessageId, 'Mail me at a@b.com');

        return false;
    });

    [$stream, $replyRecorder, $recorder] = tlsStream($source, $context, $runner);
    $events = iterator_to_array($stream, preserve_keys: false);

    $replace = collect($events)->first(fn (StreamEvent $e): bool => $e->type === EventType::Replace);

    expect($replace)->not->toBeNull()
        ->and($replace->data['text'])->toBe('Mail me at [EMAIL]')
        ->and($replyRecorder->saved)->toBe(['Mail me at [EMAIL]'])
        ->and($recorder->samples)->toBe(['Mail me at [EMAIL]'])
        ->and(app(RequestDedupeStore::class)->find($context->requestData->requestId)->content)->toBe('Mail me at [EMAIL]')
        ->and(end($events)->data['reason'])->toBe('complete');
})->with('tool loop sources');

it('redacts the partial answer it saves after the client disconnects', function (string $source): void {
    $context = TestChatContext::make();
    $runner = new ScriptedToolLoopRunner(function (ChatContext $ctx): Generator {
        $ctx->appendStreamedMessage('Mail me at a@b.com');
        yield StreamEvent::delta($ctx->assistantMessageId, 'Mail me at');
        throw new ClientDisconnected;
    });

    [$stream, $replyRecorder] = tlsStream($source, $context, $runner);
    iterator_to_array($stream, preserve_keys: false);

    expect($replyRecorder->saved)->toBe(['Mail me at [EMAIL]'])
        ->and(app(RequestDedupeStore::class)->find($context->requestData->requestId)->content)->toBe('Mail me at [EMAIL]');
})->with('tool loop sources');

it('holds the answer until the output guards checked it, then sends it in one piece', function (string $source): void {
    $context = TestChatContext::make();
    $context->requestData->agent->setSetting('check_before_showing', true, 'guardrails');
    $runner = new ScriptedToolLoopRunner(function (ChatContext $ctx): Generator {
        $ctx->appendStreamedMessage('Mail me at a@b.com');
        yield StreamEvent::delta($ctx->assistantMessageId, 'Mail me at a@b.com');

        return false;
    });

    [$stream] = tlsStream($source, $context, $runner);
    $events = collect(iterator_to_array($stream, preserve_keys: false));
    $deltas = $events->filter(fn (StreamEvent $e): bool => $e->type === EventType::Delta)->values();

    expect($deltas)->toHaveCount(1)
        ->and($deltas[0]->data['text'])->toBe('Mail me at [EMAIL]')
        ->and($events->contains(fn (StreamEvent $e): bool => $e->type === EventType::Replace))->toBeFalse();
})->with('tool loop sources');

it('keeps what the model wrote before its tool calls when the output guards leave it as it is', function (string $source): void {
    $context = TestChatContext::make();
    $runner = new ScriptedToolLoopRunner(function (ChatContext $ctx): Generator {
        $ctx->addTextStep('Let me look up your event types.');
        $ctx->appendStreamedMessage('Found it.');
        yield StreamEvent::delta($ctx->assistantMessageId, 'Found it.');

        return false;
    });

    [$stream] = tlsStream($source, $context, $runner);
    iterator_to_array($stream, preserve_keys: false);

    expect($context->stepText())->toBe('Let me look up your event types.');
})->with('tool loop sources');

it('drops step text the output guards would change, before the steps are saved', function (string $source): void {
    $context = TestChatContext::make();
    $runner = new ScriptedToolLoopRunner(function (ChatContext $ctx): Generator {
        $ctx->addTextStep('Let me mail a@b.com.');
        $ctx->addToolStep(tlsToolCall());
        $ctx->appendStreamedMessage('Sent.');
        yield StreamEvent::delta($ctx->assistantMessageId, 'Sent.');

        return false;
    });

    [$stream] = tlsStream($source, $context, $runner);
    iterator_to_array($stream, preserve_keys: false);

    // The tool step stays: it never carries model text.
    expect($context->stepText())->toBe('')
        ->and($context->steps)->toHaveCount(1)
        ->and($context->steps[0]->label)->toBe('Cal.com');
})->with('tool loop sources');

it('drops step text that was held for the output guards and never shown', function (string $source): void {
    $context = TestChatContext::make();
    $context->requestData->agent->setSetting('check_before_showing', true, 'guardrails');
    $runner = new ScriptedToolLoopRunner(function (ChatContext $ctx): Generator {
        $ctx->addTextStep('Let me look up your event types.');
        $ctx->appendStreamedMessage('Found it.');
        yield StreamEvent::delta($ctx->assistantMessageId, 'Found it.');

        return false;
    });

    [$stream] = tlsStream($source, $context, $runner);
    iterator_to_array($stream, preserve_keys: false);

    expect($context->steps)->toBe([]);
})->with('tool loop sources');

function tlsToolCall(): PendingToolCallData
{
    return new PendingToolCallData(
        toolCallId: 'call_1',
        name: 'mcp__cal__list_event_types',
        arguments: '{}',
        requiresApproval: false,
        access: null,
        kind: ToolKind::Mcp,
        label: 'Cal.com',
    );
}
