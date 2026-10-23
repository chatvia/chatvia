<?php

use App\Identity\Workspace\Models\MessageLimit;
use Domain\Agent\Models\Agent;
use Domain\Conversation\Data\Memory\ChatScopeData;
use Domain\Conversation\Data\Turn\SourceRefData;
use Domain\Conversation\Data\Turn\StreamRequestData;
use Domain\Conversation\Data\Turn\StreamRequestMessageData;
use Domain\Conversation\Jobs\StoreMessageJob;
use Domain\Conversation\Services\AgentChatRateLimiter;
use Domain\Conversation\Services\ChatStreamService;
use Domain\Conversation\Services\Memory\ChatMemory;
use Domain\Conversation\Services\Streaming\Requests\RequestDedupeStore;
use Domain\Conversation\Services\Streaming\Sse\ErrorCode;
use Domain\Conversation\Services\Streaming\Sse\EventType;
use Domain\Conversation\Services\Streaming\Sse\StreamEvent;
use Domain\Conversation\Services\Tooling\ToolLoopRunner;
use Domain\Llm\Models\Llm;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\Support\Conversation\ScriptedToolLoopRunner;

/**
 * A retried request (the same request id) gets the answer it already
 * received, replayed from the remembered snapshot: no model call, nothing
 * stored, nothing counted.
 */
beforeEach(function (): void {
    Bus::fake();
    $this->runner = ScriptedToolLoopRunner::never();
    app()->instance(ToolLoopRunner::class, $this->runner);

    $this->agent = Agent::factory()->create(['llm_id' => Llm::factory()->create()->id]);
    $this->scope = new ChatScopeData($this->agent, (string) Str::uuid(), (string) Str::uuid());
});

/** @return list<StreamEvent> */
function replayStream(string $requestId): array
{
    return iterator_to_array(app(ChatStreamService::class)->stream(new StreamRequestData(
        agent: test()->agent,
        message: new StreamRequestMessageData(model: test()->agent->llm, message: 'When do you open?'),
        externalSessionId: test()->scope->sessionId,
        conversationId: test()->scope->conversationId,
        saveUserMessageImmediately: true,
        requestId: $requestId,
    )), preserve_keys: false);
}

it('replays the remembered answer of a retried request without asking the model', function (): void {
    $sources = [new SourceRefData(id: 'chunk-1', label: 'Opening hours', kind: 'text', url: null, page: null, passages: 1)];
    app(RequestDedupeStore::class)->remember('req-retry', 'msg-earlier', 'We open at nine.', 'en', $sources);
    // Even a workspace at its limit gets the answer it was already given.
    MessageLimit::factory()->reached()->create(['workspace_id' => $this->agent->workspace_id]);

    $events = replayStream('req-retry');

    expect(array_map(static fn (StreamEvent $e): EventType => $e->type, $events))->toBe([EventType::Start, EventType::Delta, EventType::Sources, EventType::Done])
        ->and($events[0]->data)->toMatchArray(['assistant_message_id' => 'msg-earlier', 'request_id' => 'req-retry', 'language' => 'en', 'replayed' => true])
        ->and($events[1]->data['text'])->toBe('We open at nine.')
        ->and(collect($events[2]->data['sources'])->toArray())->toBe(collect($sources)->toArray())
        ->and(collect($events)->every(fn (StreamEvent $e): bool => $e->id === 'msg-earlier'))->toBeTrue()
        ->and($this->runner->runs)->toBe([])
        ->and(app(ChatMemory::class)->withScope($this->scope)->getMessagesForConversation())->toBe([]);

    Bus::assertNotDispatched(StoreMessageJob::class);
    Http::assertNothingSent();
});

it('answers afresh when the remembered answer is empty', function (): void {
    app(RequestDedupeStore::class)->remember('req-empty', 'msg-earlier', '', null);
    // The gate after the replay refuses the turn, so a turn past the replay ends there.
    $this->mock(AgentChatRateLimiter::class)->shouldReceive('visitorExceeded')->andReturnTrue();

    $events = replayStream('req-empty');

    expect(collect($events)->first(fn (StreamEvent $e): bool => $e->type === EventType::Error)->data['code'])->toBe(ErrorCode::RateLimited->value)
        ->and(collect($events)->contains(fn (StreamEvent $e): bool => ($e->data['replayed'] ?? false) === true))->toBeFalse();
});
