<?php

use App\Http\Responses\SseResponder;
use App\Identity\Workspace\Models\User;
use Domain\Agent\Models\Agent;
use Domain\Conversation\Enums\ConversationSource;
use Domain\Conversation\Models\AgentActivity;
use Domain\Conversation\Models\Conversation;
use Domain\Conversation\Models\Message;
use Domain\Conversation\Models\ToolApprovalCheckpoint;
use Domain\Integration\Function\Models\AgentFunction;
use Domain\Knowledge\Embedding\Contracts\EmbeddingsProviderInterface;
use Domain\Knowledge\Embedding\Contracts\Reranker;
use Domain\Knowledge\Embedding\Rerank\NullReranker;
use Domain\Llm\Contracts\LlmProviderInterface;
use Domain\Membership\Team\Services\TeamService;
use GuzzleHttp\Psr7\Response as PsrResponse;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Psr\Http\Message\ResponseInterface;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Integration coverage for a full tool-using conversation turn through the
 * public SSE endpoint — the seam the deep ToolLoopRunner unit suite cannot
 * see. One POST to chat.stream drives the real middleware stack, the
 * StreamRequestData factory, ChatService context build, LlmSource envelope,
 * ToolLoopRunner, RoutingToolDispatcher → HttpToolDispatcher (webhook via
 * Http::fake), and — with the queue running sync — the StoreMessageJob /
 * audit-listener persistence that normally happens on Horizon workers.
 *
 * The LLM is faked at the provider boundary with the exact OpenAI-compatible
 * SSE wire format the transformer parses: the first stream emits a
 * `delta.tool_calls` chunk, the follow-up stream answers in plain text.
 *
 * The SseResponder binding is swapped for a buffer-friendly subclass: the real
 * pump() unconditionally closes every PHP output buffer (`while (ob_get_level()
 * > 0) ob_end_clean()`), which destroys PHPUnit's own output-capture buffer and
 * makes TestResponse::streamedContent() unusable in-process. The override emits
 * the same StreamEvent envelope (withSeq()->toLine()) without the SAPI buffer /
 * flush plumbing, so everything upstream of the transport stays real.
 */
beforeEach(function (): void {
    // StoreMessageJob and the queued analytics/audit
    // listeners must run inline so the test can assert persisted state.
    config(['queue.default' => 'sync']);

    app()->instance(SseResponder::class, new class extends SseResponder
    {
        public function respond(iterable $events): StreamedResponse
        {
            return new StreamedResponse(function () use ($events): void {
                $seq = 0;
                foreach ($events as $event) {
                    echo $event->withSeq($seq++)->toLine();
                }
            }, 200, ['Content-Type' => 'text/event-stream']);
        }
    });
});

/**
 * Build one OpenAI-compatible SSE body: each payload becomes a `data:` chunk,
 * terminated by `data: [DONE]` — the exact shape OpenAiCompatibleTransformer
 * parses off a live provider stream.
 *
 * @param  array<int, array<string, mixed>>  $payloads
 */
function toolLoopSseBody(array $payloads): string
{
    $body = '';
    foreach ($payloads as $payload) {
        $body .= 'data: '.json_encode($payload)."\n\n";
    }

    return $body."data: [DONE]\n\n";
}

/**
 * Bind an LLM provider fake that serves the given SSE bodies one per stream()
 * call — the tool loop calls stream() once per iteration, so body N is model
 * turn N. Also fakes the embeddings provider (query embedding runs while
 * building the turn and during tool routing).
 */
function toolLoopBindLlm(string ...$sseBodies): void
{
    $fake = new class($sseBodies) implements LlmProviderInterface
    {
        /** @param array<int, string> $bodies */
        public function __construct(private array $bodies) {}

        public function stream(string $model, array $messages, array $settings = []): ResponseInterface
        {
            $body = array_shift($this->bodies) ?? "data: [DONE]\n\n";

            return new PsrResponse(200, ['Content-Type' => 'text/event-stream'], $body);
        }

        public function client(): PendingRequest
        {
            throw new RuntimeException('client() not used');
        }

        public function completion(array $data): never
        {
            throw new RuntimeException('completion() not used');
        }

        public function headers(): array
        {
            return [];
        }

        public function getGenerationCost(string $generationId): ?array
        {
            return null;
        }

        public function getCompletionEndpoint(): string
        {
            return 'https://fake.test/v1/chat/completions';
        }

        public function supportsCostTracking(): bool
        {
            return false;
        }
    };

    bindFakeLlmProvider($fake);

    app()->instance(EmbeddingsProviderInterface::class, new class implements EmbeddingsProviderInterface
    {
        public function embed(string $text, bool $returnAsText = false): array
        {
            return array_fill(0, 1024, 0.1);
        }

        public function embedQuery(string $text, bool $returnAsText = false): array
        {
            return array_fill(0, 1024, 0.1);
        }

        public function embedBatch(array $texts): array
        {
            return array_map(fn (string $text): array => $this->embed($text), $texts);
        }

        public function getDimensions(): int
        {
            return 1024;
        }

        public function getModelName(): string
        {
            return 'fake-embed';
        }
    });

    // No reranking service here: results keep their unranked order.
    app()->instance(Reranker::class, new NullReranker);
}

/**
 * The canonical two-turn tool conversation: model turn 1 requests
 * get_order_status(order_id=1234) via delta.tool_calls, turn 2 answers in
 * plain text (with an inline usage chunk, as providers send when
 * stream_options.include_usage is on).
 */
function toolLoopBindTwoTurnLlm(): void
{
    toolLoopBindLlm(
        toolLoopSseBody([[
            'id' => 'gen-tool-iteration',
            'choices' => [['delta' => ['tool_calls' => [[
                'index' => 0,
                'id' => 'call_order_1',
                'function' => ['name' => 'get_order_status', 'arguments' => '{"order_id":"1234"}'],
            ]]]]],
        ]]),
        toolLoopSseBody([
            ['id' => 'gen-final-answer', 'choices' => [['delta' => ['content' => 'Your order 1234 ']]]],
            ['choices' => [['delta' => ['content' => 'has shipped.']]]],
            ['choices' => [], 'usage' => ['prompt_tokens' => 42, 'completion_tokens' => 12, 'total_tokens' => 54]],
        ]),
    );
}

/** A public agent of a fresh workspace. */
function toolLoopAgent(): Agent
{
    $owner = User::factory()->create();
    app(TeamService::class)->createDefaultTeam($owner);

    return Agent::create([
        'workspace_id' => $owner->workspace()->id,
        'team_id' => $owner->primaryTeam()->id,
        'name' => 'Tool Bot',
        'external_id' => Str::random(14),
        'slug' => Str::lower(Str::random(12)),
        'system_instructions' => 'You are a helpful assistant.',
        'is_public' => true,
    ]);
}

/**
 * A webhook FunctionTool attached to the agent. Auto-approval is the stored
 * `functions.requires_approval = false` flag — the same column the approval
 * policy gates on at dispatch time (the DB default is true / gated).
 */
function toolLoopWebhookFunction(Agent $agent): AgentFunction
{
    $function = AgentFunction::create([
        'workspace_id' => $agent->workspace_id,
        'internal_name' => 'get_order_status',
        'display_name' => 'Order status',
        'description' => 'Look up the current status of a customer order by its id.',
        'parameters_schema' => [
            'type' => 'object',
            'properties' => ['order_id' => ['type' => 'string']],
            'required' => ['order_id'],
        ],
        'webhook_url' => 'https://orders.example.test/status',
        'webhook_method' => 'POST',
        'requires_approval' => false,
        'is_enabled' => true,
    ]);

    $function->agents()->attach($agent->id);

    return $function;
}

/**
 * Parse the streamed SSE body back into the envelope's decoded event frames.
 *
 * @return Collection<int, array<string, mixed>>
 */
function toolLoopParseEvents(string $sse): Collection
{
    return collect(explode("\n\n", trim($sse)))
        ->filter(fn (string $frame): bool => str_starts_with($frame, 'data: '))
        ->map(fn (string $frame): array => json_decode(substr($frame, 6), true, 512, JSON_THROW_ON_ERROR))
        ->values();
}

it('runs an auto-approved webhook tool mid-stream and persists the assistant reply with its tool-call record', function (): void {
    toolLoopBindTwoTurnLlm();
    Http::fake([
        'orders.example.test/*' => Http::response(['status' => 'shipped'], 200),
    ]);

    $agent = toolLoopAgent();
    $function = toolLoopWebhookFunction($agent);

    $conversationId = (string) Str::uuid();
    $response = $this->post(
        "/chat/{$agent->external_id}/stream/{$conversationId}",
        ['message' => 'What is the status of order 1234?'],
        ['Accept' => 'text/event-stream'],
    );

    $response->assertOk();
    expect($response->baseResponse)->toBeInstanceOf(StreamedResponse::class);

    $events = toolLoopParseEvents($response->streamedContent());

    // Envelope: start → tool start/end (ran inline, no approval pause) →
    // answer deltas → done complete.
    expect($events->where('type', 'start'))->not->toBeEmpty();

    $toolEvents = $events->where('type', 'tool')->values();
    expect($toolEvents->pluck('data.phase')->all())->toBe(['start', 'end'])
        ->and($toolEvents[0]['data']['name'])->toBe('get_order_status')
        ->and($toolEvents[0]['data']['args'])->toBe('{"order_id":"1234"}')
        ->and($toolEvents[1]['data']['result'])->toContain('shipped');

    $answer = $events->where('type', 'delta')->pluck('data.text')->implode('');
    expect($answer)->toBe('Your order 1234 has shipped.');

    $done = $events->where('type', 'done')->last();
    expect($done['data']['reason'])->toBe('complete');

    // The webhook actually received the model's arguments.
    Http::assertSent(fn (ClientRequest $request): bool => str_starts_with($request->url(), 'https://orders.example.test/status')
        && $request['order_id'] === '1234');

    // Persisted state: the conversation was minted for the hosted-chat surface
    // and the assistant message records which tool ran (metadata.tool_calls is
    // what the sticky-tool resolver reads on follow-up turns).
    $conversation = Conversation::where('external_id', $conversationId)->first();
    expect($conversation)->not->toBeNull()
        ->and($conversation->source)->toBe(ConversationSource::HostedChat);

    $assistantMessages = Message::query()
        ->where('chat_conversation_id', $conversation->id)
        ->where('role', 'assistant')
        ->get();

    // Only the final post-tool answer is persisted — the intermediate
    // tool-requesting model turn never becomes a chat_messages row.
    expect($assistantMessages)->toHaveCount(1)
        ->and($assistantMessages[0]->content)->toBe('Your order 1234 has shipped.')
        ->and($assistantMessages[0]->metadata['tool_calls'])->toBe(['get_order_status'])
        ->and($assistantMessages[0]->prompt_tokens)->toBe(42)
        ->and($assistantMessages[0]->completion_tokens)->toBe(12);

    // requires_approval=false means the turn never paused for a human.
    expect(ToolApprovalCheckpoint::where('agent_id', $agent->id)->count())->toBe(0);

    // The governance audit recorded the auto-approved dispatch.
    $audit = AgentActivity::where('agent_id', $agent->id)->get();
    expect($audit)->toHaveCount(1)
        ->and($audit[0]->tool_name)->toBe('get_order_status')
        ->and($audit[0]->outcome)->toBe('auto')
        ->and((bool) $audit[0]->gated)->toBeFalse()
        ->and((bool) $audit[0]->is_error)->toBeFalse();

    // Invocation counters: global on the function row, per-agent on the pivot.
    expect($function->refresh()->invocation_count)->toBe(1)
        ->and((int) DB::table('agent_function')
            ->where('agent_id', $agent->id)
            ->where('function_id', $function->id)
            ->value('invocation_count'))->toBe(1);
});

it('persists both the user and assistant messages of a tool-using turn in the same conversation', function (): void {
    toolLoopBindTwoTurnLlm();
    Http::fake([
        'orders.example.test/*' => Http::response(['status' => 'shipped'], 200),
    ]);

    $agent = toolLoopAgent();
    toolLoopWebhookFunction($agent);

    $conversationId = (string) Str::uuid();
    $response = $this->post(
        "/chat/{$agent->external_id}/stream/{$conversationId}",
        [
            'message' => 'What is the status of order 1234?',
            // The client flag the hosted-chat surface sends when the user turn
            // was not pre-persisted by conversations.create — the engine then
            // stores it through the same StoreMessageAction path as the reply.
            'save_user_message_immediately' => true,
        ],
        ['Accept' => 'text/event-stream'],
    );

    $response->assertOk();

    // The user turn is persisted while the request is being prepared — before
    // the stream body is even consumed.
    $conversation = Conversation::where('external_id', $conversationId)->first();
    expect($conversation)->not->toBeNull();

    $userMessages = Message::query()
        ->where('chat_conversation_id', $conversation->id)
        ->where('role', 'user')
        ->get();
    expect($userMessages)->toHaveCount(1)
        ->and($userMessages[0]->content)->toBe('What is the status of order 1234?');

    // Consume the stream: tool dispatch + final answer + assistant persistence.
    // NOTE: on a stale test DB missing chat_attachments.pages this fails here
    // with done.reason=error — pdfPageCredits() sums that column once the
    // conversation pre-exists. Re-migrate the testing DB and it passes.
    $events = toolLoopParseEvents($response->streamedContent());
    $done = $events->where('type', 'done')->last();
    expect($done['data']['reason'])->toBe('complete');

    $assistantMessages = Message::query()
        ->where('chat_conversation_id', $conversation->id)
        ->where('role', 'assistant')
        ->get();

    expect($assistantMessages)->toHaveCount(1)
        ->and($assistantMessages[0]->content)->toBe('Your order 1234 has shipped.')
        ->and($assistantMessages[0]->metadata['tool_calls'])->toBe(['get_order_status']);
});
