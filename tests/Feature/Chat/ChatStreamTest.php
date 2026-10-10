<?php

use App\Identity\Workspace\Models\User;
use Domain\Agent\Models\Agent;
use Domain\Agent\Notifications\AgentRateLimitNotification;
use Domain\Conversation\Events\AgentRateLimitExceeded;
use Domain\Knowledge\Embedding\Contracts\EmbeddingsProviderInterface;
use Domain\Knowledge\Embedding\Contracts\Reranker;
use Domain\Knowledge\Embedding\Rerank\NullReranker;
use Domain\Llm\Contracts\LlmProviderInterface;
use Domain\Membership\Team\Services\TeamService;
use GuzzleHttp\Psr7\Response as PsrResponse;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Psr\Http\Message\ResponseInterface;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * HTTP-path coverage for the streaming chat endpoint. Proves that a valid POST
 * makes it all the way through the `chat` middleware stack — agent resolution,
 * the message-limit gate — and the StreamRequestData factory
 * + ChatStreamService, emerging as a well-formed SSE StreamedResponse (200,
 * text/event-stream). This request path previously had zero test coverage.
 *
 * The streaming ENGINE internals it funnels into — the start/sources/done
 * envelope, exception→error-event mapping, partial-save + replay on disconnect,
 * and the OpenAI-style SSE transform — are covered by LlmSourceTest and
 * OpenAiCompatibleTransformerTest. The LLM provider is faked here purely so the
 * endpoint never reaches for a real model if the body is ever consumed.
 */
function bindFakeStreamingLlm(string ...$tokens): void
{
    $first = array_shift($tokens) ?? 'Hello';
    $sse = 'data: '.json_encode(['id' => 'gen-test', 'choices' => [['delta' => ['content' => $first]]]])."\n\n";
    foreach ($tokens as $token) {
        $sse .= 'data: '.json_encode(['choices' => [['delta' => ['content' => $token]]]])."\n\n";
    }
    $sse .= "data: [DONE]\n\n";

    $fake = new readonly class($sse) implements LlmProviderInterface
    {
        public function __construct(private string $sse) {}

        public function stream(string $model, array $messages, array $settings = []): ResponseInterface
        {
            return new PsrResponse(200, ['Content-Type' => 'text/event-stream'], $this->sse);
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

    app()->instance(LlmProviderInterface::class, $fake);

    // The RAG query-embedding runs while building the turn, so fake it too —
    // otherwise the test env has no embedding API key and the request 500s.
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

/** A public agent of a fresh workspace. */
function publicStreamingAgent(): Agent
{
    $owner = User::factory()->create();
    app(TeamService::class)->createDefaultTeam($owner);

    return Agent::create([
        'user_id' => $owner->id,
        'team_id' => $owner->primaryTeam()->id,
        'name' => 'Stream Bot',
        'external_id' => Str::random(14),
        'sub_domain' => Str::lower(Str::random(12)),
        'system_instructions' => 'You are a helpful assistant.',
        'is_public' => true,
    ]);
}

it('returns a 200 SSE stream for an authorized chat turn', function (): void {
    bindFakeStreamingLlm('Hello', ' there', '!');

    $agent = publicStreamingAgent();

    $conversationId = (string) Str::uuid();

    $response = $this->post(
        "https://chatvia.test/chat/stream/{$conversationId}?agent={$agent->external_id}",
        ['message' => 'Hi there'],
        ['Accept' => 'text/event-stream'],
    );

    $response->assertOk();

    expect($response->headers->get('Content-Type'))->toContain('text/event-stream')
        ->and($response->baseResponse)->toBeInstanceOf(StreamedResponse::class);
});

it('rejects a chat-stream request with no message (validation)', function (): void {
    $agent = publicStreamingAgent();

    $this->postJson(
        'https://chatvia.test/chat/stream/'.Str::uuid()."?agent={$agent->external_id}",
        [],
    )->assertStatus(422)->assertJsonValidationErrors(['message']);
});

it('dispatches a rate-limit event once the agent exceeds its per-minute cap', function (): void {
    Event::fake([AgentRateLimitExceeded::class]);

    $agent = publicStreamingAgent();
    $agent->setSetting(key: 'chat_rate_limit_per_minute', value: 1, group: 'security');
    $agent->setSetting(key: 'notify_owner_on_rate_limit', value: true, group: 'security');

    // The per-agent gate runs synchronously before the SSE body streams, so the
    // bare POST exercises it — no need to consume the stream (which would fight
    // the streamer's own output buffering).
    $send = fn (): TestResponse => $this->post(
        'https://chatvia.test/chat/stream/'.Str::uuid()."?agent={$agent->external_id}",
        ['message' => 'Hi there'],
        ['Accept' => 'text/event-stream'],
    );

    $send()->assertOk();
    $send()->assertOk();

    Event::assertDispatched(
        AgentRateLimitExceeded::class,
        fn (AgentRateLimitExceeded $e): bool => $e->agent->is($agent) && $e->limitPerMinute === 1,
    );
});

it('emails the owner when a rate-limited agent has alerts enabled', function (): void {
    Notification::fake();

    $agent = publicStreamingAgent();
    $agent->setSetting(key: 'chat_rate_limit_per_minute', value: 1, group: 'security');
    $agent->setSetting(key: 'notify_owner_on_rate_limit', value: true, group: 'security');

    $send = fn (): TestResponse => $this->post(
        'https://chatvia.test/chat/stream/'.Str::uuid()."?agent={$agent->external_id}",
        ['message' => 'Hi there'],
        ['Accept' => 'text/event-stream'],
    );

    $send();
    $send();

    Notification::assertSentTo(
        $agent->user,
        AgentRateLimitNotification::class,
    );
});

it('does not email the owner when rate-limit alerts are disabled', function (): void {
    Notification::fake();

    $agent = publicStreamingAgent();
    $agent->setSetting(key: 'chat_rate_limit_per_minute', value: 1, group: 'security');

    $send = fn (): TestResponse => $this->post(
        'https://chatvia.test/chat/stream/'.Str::uuid()."?agent={$agent->external_id}",
        ['message' => 'Hi there'],
        ['Accept' => 'text/event-stream'],
    );

    $send();
    $send();

    Notification::assertNothingSent();
});
