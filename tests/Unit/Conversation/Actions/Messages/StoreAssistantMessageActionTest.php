<?php

use App\Identity\Workspace\Models\User;
use Domain\Agent\Models\Agent;
use Domain\Analytics\Events\MessageSent;
use Domain\Billing\Actions\Credits\RecordTokenUsageAction;
use Domain\Billing\Actions\Credits\StartCreditCycleAction;
use Domain\Conversation\Actions\Messages\StoreAssistantMessageAction;
use Domain\Conversation\Actions\Messages\StoreUserMessageAction;
use Domain\Conversation\Data\Messages\AssistantTurnData;
use Domain\Conversation\Data\Messages\UserTurnData;
use Domain\Conversation\Enums\ConversationSource;
use Domain\Conversation\Enums\MessageRole;
use Domain\Conversation\Jobs\GenerateConversationTitleJob;
use Domain\Conversation\Models\Conversation;
use Domain\Conversation\Services\ChatLog\ChatLogWriter;
use Domain\Llm\Contracts\LlmProviderInterface;
use Domain\Llm\Models\Llm;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Psr\Http\Message\ResponseInterface;

/**
 * Writing an assistant reply to the chat log: its usage is settled first
 * (stream usage, a provider lookup, or nothing), then the message and the
 * workspace's token log land in one transaction; analytics and the
 * conversation title follow the commit.
 */
/**
 * Minimal LlmProviderInterface stub for cost-tracking branches.
 *
 *  - $supportsCost flips supportsCostTracking()
 *  - $usage is the dollar/token shape returned by getGenerationCost()
 *    so individual tests pin precise prompt/completion/cost expectations
 */
function fakeLlmProvider(bool $supportsCost = false, ?array $usage = null): LlmProviderInterface
{
    return new readonly class($supportsCost, $usage) implements LlmProviderInterface
    {
        public function __construct(
            private bool $supportsCost,
            private ?array $usage,
        ) {}

        public function stream(string $model, array $messages, array $settings = []): ResponseInterface
        {
            throw new RuntimeException('stream() not used in StoreAssistantMessageAction tests');
        }

        public function client(): PendingRequest
        {
            throw new RuntimeException('client() not used in StoreAssistantMessageAction tests');
        }

        public function completion(array $data): never
        {
            throw new RuntimeException('completion() not used in StoreAssistantMessageAction tests');
        }

        public function headers(): array
        {
            return [];
        }

        public function getGenerationCost(string $generationId): ?array
        {
            return $this->usage;
        }

        public function getCompletionEndpoint(): string
        {
            return '';
        }

        public function supportsCostTracking(): bool
        {
            return $this->supportsCost;
        }
    };
}

function storeAssistantAction(LlmProviderInterface $provider): StoreAssistantMessageAction
{
    return new StoreAssistantMessageAction($provider, app(ChatLogWriter::class), app(RecordTokenUsageAction::class));
}

/** A stored visitor message, so the reply has its session and conversation. */
function seedUserTurn(Agent $agent, ?string $source = 'widget'): UserTurnData
{
    $turn = UserTurnData::from([
        'external_session_id' => (string) Str::uuid(),
        'external_conversation_id' => (string) Str::uuid(),
        'external_message_id' => (string) Str::uuid(),
        'model' => '',
        'message' => 'Hello, can you help me with my order?',
        'sent_at' => Carbon::parse('2026-06-05 10:00:00'),
        'source' => $source,
    ]);

    app(StoreUserMessageAction::class)->execute($agent, $turn);

    return $turn;
}

function assistantTurn(Llm $llm, UserTurnData $userTurn, array $overrides = []): AssistantTurnData
{
    return AssistantTurnData::from(array_merge([
        'external_session_id' => $userTurn->external_session_id,
        'external_conversation_id' => $userTurn->external_conversation_id,
        'external_message_id' => (string) Str::uuid(),
        'message' => 'Sure — what is the order number?',
        'model' => $llm->external_name,
        'sent_at' => Carbon::parse('2026-06-05 10:00:01'),
        'generation_id' => 'gen_'.Str::random(12),
        'response_time_ms' => 1234,
        'source' => $userTurn->source,
    ], $overrides));
}

beforeEach(function (): void {
    Event::fake([MessageSent::class]);
    Bus::fake();
});

it('computes microcent cost from total_cost dollars (sub-cent precision boundary)', function (): void {
    // 0.00000123 dollars × 100_000_000 microcents/dollar = 123 microcents.
    $agent = Agent::factory()->create();
    $llm = Llm::factory()->create(['external_name' => 'gpt-test-cost']);
    $provider = fakeLlmProvider(supportsCost: true, usage: [
        'tokens_prompt' => 42,
        'tokens_completion' => 17,
        'total_cost' => 0.00000123,
    ]);

    $turn = assistantTurn($llm, seedUserTurn($agent));
    $message = storeAssistantAction($provider)->execute($agent, $turn);

    expect($message->role)->toBe(MessageRole::Assistant)
        ->and($message->llm_id)->toBe($llm->id)
        ->and($message->prompt_tokens)->toBe(42)
        ->and($message->completion_tokens)->toBe(17)
        ->and($message->cost_in_micro_cents)->toBe(123)
        ->and($message->generation_id)->toBe($turn->generation_id)
        ->and($message->response_time_ms)->toBe(1234);

    Event::assertDispatched(MessageSent::class, fn (MessageSent $event): bool => $event->role === 'assistant'
        && $event->responseTimeMs === 1234
        && $event->promptTokens === 42);
});

it('rounds microcent cost half-up at the integer boundary', function (): void {
    $agent = Agent::factory()->create();
    $llm = Llm::factory()->create(['external_name' => 'gpt-rounding-test']);

    foreach ([
        ['dollars' => 0.00000123456, 'expected' => 123],
        ['dollars' => 0.00000123567, 'expected' => 124],
    ] as $case) {
        $provider = fakeLlmProvider(supportsCost: true, usage: [
            'tokens_prompt' => 1,
            'tokens_completion' => 1,
            'total_cost' => $case['dollars'],
        ]);

        $message = storeAssistantAction($provider)->execute($agent, assistantTurn($llm, seedUserTurn($agent)));

        expect($message->cost_in_micro_cents)->toBe($case['expected']);
    }
});

it('leaves cost and tokens null when the provider does not support cost tracking', function (): void {
    $agent = Agent::factory()->create();
    $llm = Llm::factory()->create(['external_name' => 'gpt-no-cost-tracking']);

    $message = storeAssistantAction(fakeLlmProvider(supportsCost: false))
        ->execute($agent, assistantTurn($llm, seedUserTurn($agent)));

    expect($message->cost_in_micro_cents)->toBeNull()
        ->and($message->prompt_tokens)->toBeNull()
        ->and($message->completion_tokens)->toBeNull();
});

it('prefers inline stream usage over the provider cost lookup', function (): void {
    $agent = Agent::factory()->create();
    $llm = Llm::factory()->create(['external_name' => 'gpt-inline-usage']);

    // The provider WOULD return different numbers if asked.
    $provider = fakeLlmProvider(supportsCost: true, usage: [
        'tokens_prompt' => 999,
        'tokens_completion' => 999,
        'total_cost' => 1.23,
    ]);

    $message = storeAssistantAction($provider)->execute($agent, assistantTurn($llm, seedUserTurn($agent), [
        'prompt_tokens' => 1200,
        'completion_tokens' => 300,
    ]));

    expect($message->prompt_tokens)->toBe(1200)
        ->and($message->completion_tokens)->toBe(300)
        // inline usage carries no dollar cost
        ->and($message->cost_in_micro_cents)->toBeNull();
});

it('logs the reply tokens against the workspace owner billing cycle', function (): void {
    $agent = Agent::factory()->create();
    $owner = User::find($agent->user_id);
    $credit = app(StartCreditCycleAction::class)->execute($owner, 4000, now()->addMonth());
    $llm = Llm::factory()->create(['external_name' => 'gpt-token-log']);

    storeAssistantAction(fakeLlmProvider(supportsCost: true, usage: [
        'tokens_prompt' => 120,
        'tokens_completion' => 80,
        'total_cost' => 0.0,
    ]))->execute($agent, assistantTurn($llm, seedUserTurn($agent)));

    expect($credit->usage()->first()->tokens)->toBe(200);
});

it('keeps tool artifacts and tool names in the message metadata', function (): void {
    $agent = Agent::factory()->create();
    $llm = Llm::factory()->create();

    $message = storeAssistantAction(fakeLlmProvider())->execute($agent, assistantTurn($llm, seedUserTurn($agent), [
        'artifacts' => [['kind' => 'echarts_option', 'data' => ['series' => []]]],
        'tool_calls' => ['mcp__charts__render'],
    ]));

    expect($message->metadata)->toBe([
        'artifacts' => [['kind' => 'echarts_option', 'data' => ['series' => []]]],
        'tool_calls' => ['mcp__charts__render'],
    ]);
});

it('queues the title after the first reply outside the hosted chat', function (): void {
    $agent = Agent::factory()->create();
    $llm = Llm::factory()->create();
    $userTurn = seedUserTurn($agent);

    Conversation::query()->where('external_id', $userTurn->external_conversation_id)->update(['title' => null]);

    storeAssistantAction(fakeLlmProvider())->execute($agent, assistantTurn($llm, $userTurn));

    Bus::assertDispatched(GenerateConversationTitleJob::class);
});

it('leaves titling to the hosted chat client', function (): void {
    $agent = Agent::factory()->create();
    $llm = Llm::factory()->create();
    $userTurn = seedUserTurn($agent, source: null);

    expect(Conversation::query()->firstWhere('external_id', $userTurn->external_conversation_id)->source)
        ->toBe(ConversationSource::HostedChat);

    storeAssistantAction(fakeLlmProvider())->execute($agent, assistantTurn($llm, $userTurn));

    Bus::assertNotDispatched(GenerateConversationTitleJob::class);
});
