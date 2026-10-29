<?php

use Domain\Agent\Models\Agent;
use Domain\Conversation\Data\Turn\RetrievedSourceData;
use Domain\Conversation\Services\ChatContext;
use Domain\Conversation\Services\Streaming\Exceptions\ClientDisconnected;
use Domain\Conversation\Services\Streaming\Exceptions\TurnStopped;
use Domain\Conversation\Services\Streaming\Exceptions\UpstreamStreamClosed;
use Domain\Conversation\Services\Streaming\Requests\RequestDedupeStore;
use Domain\Conversation\Services\Streaming\Sources\LlmSource;
use Domain\Conversation\Services\Streaming\Sse\ErrorCode;
use Domain\Conversation\Services\Streaming\Sse\EventType;
use Domain\Conversation\Services\Streaming\Sse\StreamEvent;
use Domain\Conversation\Services\Tooling\ToolLoopRunner;
use Domain\Conversation\Services\Turn\CitationMarkers;
use Domain\Guardrail\Services\GuardrailInspector;
use Domain\Guardrail\Services\GuardrailRecorder;
use Domain\Knowledge\Retrieval\Enums\SourceKind;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Tests\Support\Conversation\NoToolSelector;
use Tests\Support\Conversation\RecordingReplyRecorder;
use Tests\Support\Conversation\ScriptedToolLoopRunner;
use Tests\Support\Conversation\TestChatContext;
use Tests\Support\Llm\CannedLlmProvider;
use Tests\Support\Llm\FakeLlmProviders;

/**
 * LlmSource streams a turn in the shared envelope: the start event, the
 * sources only when the client shows them, the done event, the mapping of
 * exceptions to error events, and the partial save and replay entry when the
 * client disconnects or the visitor stops the turn. The model streaming and
 * the tool loop run inside ToolLoopRunner; these tests script it and check
 * what LlmSource does around it.
 */
/**
 * A turn of an agent with its guardrails switched off, so the real output
 * guard returns the answer unchanged without a classifier call.
 */
function lsContext(bool $streamSources = false): ChatContext
{
    $agent = Agent::factory()->create();
    $agent->setSetting('enabled', false, 'guardrails');

    return TestChatContext::make(agent: $agent, streamSources: $streamSources);
}

/**
 * Runs one scripted turn and returns its events and the replies it saved.
 *
 * @return array{0: list<StreamEvent>, 1: RecordingReplyRecorder}
 */
function lsRun(ToolLoopRunner $runner, ChatContext $context): array
{
    $recorder = new RecordingReplyRecorder;
    $source = new LlmSource(
        providers: new FakeLlmProviders(new CannedLlmProvider),
        replyRecorder: $recorder,
        dedupe: new RequestDedupeStore,
        toolSelector: new NoToolSelector,
        runner: $runner,
        guardrails: app(GuardrailInspector::class),
        guardrailRecorder: app(GuardrailRecorder::class),
        citations: app(CitationMarkers::class),
    );

    return [iterator_to_array($source->events($context, [['role' => 'user', 'content' => 'Hi']]), preserve_keys: false), $recorder];
}

/** @param  list<StreamEvent>  $events */
function lsTypes(array $events): array
{
    return array_map(static fn (StreamEvent $e): EventType => $e->type, $events);
}

function lsRetrievedSource(): RetrievedSourceData
{
    return new RetrievedSourceData(
        id: 11,
        external_id: 'chunk-11',
        content: 'Returns are free within 30 days.',
        relevance: 0.82,
        label: 'Returns policy',
        kind: SourceKind::Website,
        url: 'https://shop.test/returns',
        page: null,
        sourceable_type: 'source_website',
        sourceable_id: 7,
    );
}

/** A runner that streams the given text, sets the context up, then throws the given exception. */
function lsCutShort(string $text, Throwable $cut, ?Closure $before = null): ScriptedToolLoopRunner
{
    return new ScriptedToolLoopRunner(function (ChatContext $ctx) use ($text, $cut, $before): Generator {
        if ($before instanceof Closure) {
            $before($ctx);
        }
        yield StreamEvent::delta($ctx->assistantMessageId, $text);
        if ($text !== '') {
            $ctx->appendStreamedMessage($text);
        }

        throw $cut;
    });
}

beforeEach(function (): void {
    Cache::flush();
    Log::spy();
});

it('streams a turn between start and done, with the sources only when the client shows them', function (): void {
    $shown = lsContext(streamSources: true);
    $shown->setSources(collect([lsRetrievedSource()]));
    $hidden = lsContext();
    $hidden->setSources(collect([lsRetrievedSource()]));

    [$shownEvents] = lsRun(ScriptedToolLoopRunner::answering('Returns are free.'), $shown);
    [$hiddenEvents] = lsRun(ScriptedToolLoopRunner::answering('Returns are free.'), $hidden);
    $sources = collect($shownEvents)->first(fn (StreamEvent $e): bool => $e->type === EventType::Sources);

    expect(lsTypes($shownEvents))->toBe([EventType::Start, EventType::Delta, EventType::Sources, EventType::Done])
        ->and($shownEvents[0]->data['assistant_message_id'])->toBe($shown->assistantMessageId)
        ->and(end($shownEvents)->data['reason'])->toBe('complete')
        ->and(collect($sources->data['sources'])->pluck('id')->all())->toBe(['chunk-11'])
        ->and(collect(app(RequestDedupeStore::class)->find($shown->requestData->requestId)->sources)->toArray())->toBe(collect($sources->data['sources'])->toArray())
        ->and(lsTypes($hiddenEvents))->toBe([EventType::Start, EventType::Delta, EventType::Done])
        ->and(app(RequestDedupeStore::class)->find($hidden->requestData->requestId)->sources)->toBe([]);
});

it('keeps the partial answer of a turn cut short by a disconnect or a stop, and saves an empty one only when a tool made something', function (): void {
    $disconnected = lsContext();

    [$events, $disconnectSaved] = lsRun(lsCutShort('partial reply', new ClientDisconnected), $disconnected);
    [, $stopSaved] = lsRun(lsCutShort('partial reply', new TurnStopped), lsContext());
    [, $emptySaved] = lsRun(lsCutShort('', new ClientDisconnected), lsContext());
    [, $fileSaved] = lsRun(lsCutShort('', new ClientDisconnected, function (ChatContext $ctx): void {
        $ctx->artifacts = [['kind' => 'image', 'data' => ['id' => 'm1', 'status' => 'pending']]];
    }), lsContext());
    $cached = app(RequestDedupeStore::class)->find($disconnected->requestData->requestId);

    expect(lsTypes($events))->toBe([EventType::Start, EventType::Delta])
        ->and($cached->content)->toBe('partial reply')
        ->and($cached->assistant_message_id)->toBe($disconnected->assistantMessageId)
        ->and($disconnectSaved->saved)->toBe(['partial reply'])
        ->and($stopSaved->saved)->toBe(['partial reply'])
        ->and($emptySaved->saved)->toBe([])
        ->and($fileSaved->saved)->toBe(['']);
});

it('maps a closed upstream stream to upstream_unavailable and anything else to an internal error, ending with done(error)', function (): void {
    [$closed] = lsRun(lsCutShort('partial', new UpstreamStreamClosed), lsContext());
    [$boom] = lsRun(new ScriptedToolLoopRunner(function (): Generator {
        yield from [];

        throw new RuntimeException('boom');
    }), lsContext());

    expect(lsTypes($closed))->toBe([EventType::Start, EventType::Delta, EventType::Error, EventType::Done])
        ->and($closed[2]->data['code'])->toBe(ErrorCode::UpstreamUnavailable->value)
        ->and($closed[3]->data['reason'])->toBe('error')
        ->and(lsTypes($boom))->toBe([EventType::Start, EventType::Error, EventType::Done])
        ->and($boom[1]->data['code'])->toBe(ErrorCode::Internal->value)
        ->and($boom[2]->data['reason'])->toBe('error');
});
