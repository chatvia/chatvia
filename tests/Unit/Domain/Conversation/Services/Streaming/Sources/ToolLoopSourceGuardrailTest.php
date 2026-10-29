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
    useChatFiles();
});

dataset('tool loop sources', ['llm', 'files']);

it('redacts the answer before saving and replaying it, and the partial answer saved after a disconnect', function (string $source): void {
    $context = TestChatContext::make();
    $runner = new ScriptedToolLoopRunner(function (ChatContext $ctx): Generator {
        $ctx->appendStreamedMessage('Mail me at a@b.com');
        yield StreamEvent::delta($ctx->assistantMessageId, 'Mail me at a@b.com');

        return false;
    });
    $cutContext = TestChatContext::make();
    $cut = new ScriptedToolLoopRunner(function (ChatContext $ctx): Generator {
        $ctx->appendStreamedMessage('Mail me at a@b.com');
        yield StreamEvent::delta($ctx->assistantMessageId, 'Mail me at');
        throw new ClientDisconnected;
    });

    [$stream, $replyRecorder, $recorder] = tlsStream($source, $context, $runner);
    $events = iterator_to_array($stream, preserve_keys: false);
    [$cutStream, $cutRecorder] = tlsStream($source, $cutContext, $cut);
    iterator_to_array($cutStream, preserve_keys: false);

    $replace = collect($events)->first(fn (StreamEvent $e): bool => $e->type === EventType::Replace);
    expect($replace->data['text'])->toBe('Mail me at [EMAIL]')
        ->and($replyRecorder->saved)->toBe(['Mail me at [EMAIL]'])
        ->and($recorder->samples)->toBe(['Mail me at [EMAIL]'])
        ->and(app(RequestDedupeStore::class)->find($context->requestData->requestId)->content)->toBe('Mail me at [EMAIL]')
        ->and(end($events)->data['reason'])->toBe('complete')
        ->and($cutRecorder->saved)->toBe(['Mail me at [EMAIL]'])
        ->and(app(RequestDedupeStore::class)->find($cutContext->requestData->requestId)->content)->toBe('Mail me at [EMAIL]');
})->with('tool loop sources');

it('holds the answer until the output guards checked it, sends it in one piece and drops step text never shown', function (string $source): void {
    $context = TestChatContext::make();
    $context->requestData->agent->setSetting('check_before_showing', true, 'guardrails');
    $runner = new ScriptedToolLoopRunner(function (ChatContext $ctx): Generator {
        $ctx->addTextStep('Let me look up your event types.');
        $ctx->appendStreamedMessage('Mail me at a@b.com');
        yield StreamEvent::delta($ctx->assistantMessageId, 'Mail me at a@b.com');

        return false;
    });

    [$stream] = tlsStream($source, $context, $runner);
    $events = collect(iterator_to_array($stream, preserve_keys: false));
    $deltas = $events->filter(fn (StreamEvent $e): bool => $e->type === EventType::Delta)->values();

    expect($deltas)->toHaveCount(1)
        ->and($deltas[0]->data['text'])->toBe('Mail me at [EMAIL]')
        ->and($events->contains(fn (StreamEvent $e): bool => $e->type === EventType::Replace))->toBeFalse()
        ->and($context->steps)->toBe([]);
})->with('tool loop sources');

it('keeps step text the output guards leave as it is, and drops step text they would change before it is saved', function (string $source): void {
    $kept = TestChatContext::make();
    $keptRunner = new ScriptedToolLoopRunner(function (ChatContext $ctx): Generator {
        $ctx->addTextStep('Let me look up your event types.');
        $ctx->appendStreamedMessage('Found it.');
        yield StreamEvent::delta($ctx->assistantMessageId, 'Found it.');

        return false;
    });
    $changed = TestChatContext::make();
    $changedRunner = new ScriptedToolLoopRunner(function (ChatContext $ctx): Generator {
        $ctx->addTextStep('Let me mail a@b.com.');
        $ctx->addToolStep(tlsToolCall());
        $ctx->appendStreamedMessage('Sent.');
        yield StreamEvent::delta($ctx->assistantMessageId, 'Sent.');

        return false;
    });

    iterator_to_array(tlsStream($source, $kept, $keptRunner)[0], preserve_keys: false);
    iterator_to_array(tlsStream($source, $changed, $changedRunner)[0], preserve_keys: false);

    expect($kept->stepText())->toBe('Let me look up your event types.')
        ->and($changed->stepText())->toBe('')
        ->and($changed->steps)->toHaveCount(1)
        ->and($changed->steps[0]->label)->toBe('Cal.com');
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
