<?php

use Domain\Agent\Models\Agent;
use Domain\Analytics\Events\MessageSent;
use Domain\Conversation\Actions\Messages\StoreAssistantMessageAction;
use Domain\Conversation\Actions\Messages\StoreUserMessageAction;
use Domain\Conversation\Data\Messages\AssistantTurnData;
use Domain\Conversation\Data\Messages\MessageSourceData;
use Domain\Conversation\Data\Messages\UserTurnData;
use Domain\Conversation\Enums\ConversationSource;
use Domain\Conversation\Enums\MessageRole;
use Domain\Conversation\Jobs\GenerateConversationTitleJob;
use Domain\Conversation\Models\Conversation;
use Domain\Conversation\Models\MessageSource;
use Domain\Conversation\Services\ChatLog\ChatLogWriter;
use Domain\Llm\Contracts\LlmProviderInterface;
use Domain\Llm\Models\Llm;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Tests\Support\CannedLlmProvider;
use Tests\Support\FakeLlmProviders;

/**
 * Writing an assistant reply to the chat log: its usage is settled first
 * (stream usage, a provider lookup, or nothing), then the message and the
 * workspace's token log land in one transaction; analytics and the
 * conversation title follow the commit.
 */
function storeAssistantAction(LlmProviderInterface $provider): StoreAssistantMessageAction
{
    return new StoreAssistantMessageAction(new FakeLlmProviders($provider), app(ChatLogWriter::class));
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
        'trace_id' => (string) Str::uuid(),
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
    $provider = CannedLlmProvider::costing([
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
        ->and($message->trace_id)->toBe($turn->trace_id)
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
        $provider = CannedLlmProvider::costing([
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

    $message = storeAssistantAction(new CannedLlmProvider)
        ->execute($agent, assistantTurn($llm, seedUserTurn($agent)));

    expect($message->cost_in_micro_cents)->toBeNull()
        ->and($message->prompt_tokens)->toBeNull()
        ->and($message->completion_tokens)->toBeNull();
});

it('prefers inline stream usage over the provider cost lookup', function (): void {
    $agent = Agent::factory()->create();
    $llm = Llm::factory()->create(['external_name' => 'gpt-inline-usage']);

    // The provider WOULD return different numbers if asked.
    $provider = CannedLlmProvider::costing([
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

it('keeps tool artifacts and tool names in the message metadata', function (): void {
    $agent = Agent::factory()->create();
    $llm = Llm::factory()->create();

    $message = storeAssistantAction(new CannedLlmProvider)->execute($agent, assistantTurn($llm, seedUserTurn($agent), [
        'artifacts' => [['kind' => 'echarts_option', 'data' => ['series' => []]]],
        'tool_calls' => ['mcp__charts__render'],
    ]));

    expect($message->metadata)->toBe([
        'artifacts' => [['kind' => 'echarts_option', 'data' => ['series' => []]]],
        'tool_calls' => ['mcp__charts__render'],
    ]);
});

it('stores the knowledge the answer was given, in its order', function (): void {
    $agent = Agent::factory()->create();
    $llm = Llm::factory()->create();

    $message = storeAssistantAction(new CannedLlmProvider)->execute($agent, assistantTurn($llm, seedUserTurn($agent), [
        'sources' => [
            new MessageSourceData(rank: 0, chunk_id: null, sourceable_type: 'source_website', sourceable_id: 7, score: 0.91, label: 'Returns', url: 'https://shop.test/returns'),
            new MessageSourceData(rank: 1, chunk_id: null, sourceable_type: 'source_file', sourceable_id: 8, score: 0.42, label: 'manual.pdf', page: 3),
        ],
    ]));

    expect($message->sources->map(fn (MessageSource $source): array => [$source->rank, $source->workspace_id, $source->label, $source->url, $source->page, $source->score])->all())->toBe([
        [0, $agent->workspace_id, 'Returns', 'https://shop.test/returns', null, 0.91],
        [1, $agent->workspace_id, 'manual.pdf', null, 3, 0.42],
    ]);
});

it('stores no sources for an answer given no knowledge', function (): void {
    $agent = Agent::factory()->create();
    $llm = Llm::factory()->create();

    $message = storeAssistantAction(new CannedLlmProvider)->execute($agent, assistantTurn($llm, seedUserTurn($agent)));

    expect($message->sources)->toBeEmpty();
});

it('queues the title at once for a channel, where nobody watches it', function (): void {
    $agent = Agent::factory()->create();
    $llm = Llm::factory()->create();
    $userTurn = seedUserTurn($agent);

    // A Telegram conversation (the channel runner starts it with that source).
    Conversation::query()->where('external_id', $userTurn->external_conversation_id)->update(['title' => null, 'source' => ConversationSource::Telegram]);

    storeAssistantAction(new CannedLlmProvider)->execute($agent, assistantTurn($llm, $userTurn));

    Bus::assertDispatched(GenerateConversationTitleJob::class, fn (GenerateConversationTitleJob $job): bool => $job->delay === null);
});

it('only queues a delayed fallback where the browser asks for the title itself', function (ConversationSource $source): void {
    $agent = Agent::factory()->create();
    $llm = Llm::factory()->create();
    $userTurn = seedUserTurn($agent);

    Conversation::query()->where('external_id', $userTurn->external_conversation_id)->update(['title' => null, 'source' => $source]);

    storeAssistantAction(new CannedLlmProvider)->execute($agent, assistantTurn($llm, $userTurn));

    Bus::assertDispatched(GenerateConversationTitleJob::class, fn (GenerateConversationTitleJob $job): bool => $job->delay !== null);
})->with([
    'chat widget' => [ConversationSource::Widget],
    'command bar' => [ConversationSource::Bar],
]);

it('leaves titling to the hosted chat client', function (): void {
    $agent = Agent::factory()->create();
    $llm = Llm::factory()->create();
    $userTurn = seedUserTurn($agent, source: null);

    expect(Conversation::query()->firstWhere('external_id', $userTurn->external_conversation_id)->source)
        ->toBe(ConversationSource::HostedChat);

    storeAssistantAction(new CannedLlmProvider)->execute($agent, assistantTurn($llm, $userTurn));

    Bus::assertNotDispatched(GenerateConversationTitleJob::class);
});
