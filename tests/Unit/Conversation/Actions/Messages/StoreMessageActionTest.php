<?php

use App\Identity\Workspace\Models\User;
use Domain\Agent\Models\Agent;
use Domain\Analytics\Events\MessageSent;
use Domain\Analytics\Jobs\AnalyzeConversationJob;
use Domain\Analytics\Jobs\AnalyzeMessageJob;
use Domain\Billing\Services\ChatterMessageAllowance;
use Domain\Billing\Services\UserCreditSystem;
use Domain\Conversation\Actions\Messages\StoreMessageAction;
use Domain\Conversation\Data\Messages\ChatInputPayload;
use Domain\Conversation\Data\Messages\ChatOutputPayload;
use Domain\Conversation\Events\SessionLocationUnknown;
use Domain\Conversation\Models\Message;
use Domain\Conversation\Models\Session;
use Domain\Llm\Contracts\LlmProviderInterface;
use Domain\Llm\Models\Llm;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Psr\Http\Message\ResponseInterface;

/**
 * Minimal LlmProviderInterface stub for cost-tracking branches.
 *
 *  - $supportsCost flips supportsCostTracking()
 *  - $usage is the dollar/token shape returned by getGenerationCost()
 *    so individual tests pin precise prompt/completion/cost expectations
 */
function fakeLlmProvider(bool $supportsCost = false, ?array $usage = null): LlmProviderInterface
{
    return new class($supportsCost, $usage) implements LlmProviderInterface
    {
        public function __construct(
            private readonly bool $supportsCost,
            private readonly ?array $usage,
        ) {}

        public function stream(string $model, array $messages, array $settings = []): ResponseInterface
        {
            throw new RuntimeException('stream() not used in StoreMessageAction tests');
        }

        public function client(): PendingRequest
        {
            throw new RuntimeException('client() not used in StoreMessageAction tests');
        }

        public function completion(array $data): void
        {
            throw new RuntimeException('completion() not used in StoreMessageAction tests');
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

function makeStoreMessageAction(LlmProviderInterface $provider): StoreMessageAction
{
    return new StoreMessageAction($provider, new ChatterMessageAllowance, new UserCreditSystem);
}

function makeInputPayload(Agent $agent, array $overrides = []): ChatInputPayload
{
    return ChatInputPayload::from(array_merge([
        'external_session_id' => (string) Str::uuid(),
        'external_conversation_id' => (string) Str::uuid(),
        'external_message_id' => (string) Str::uuid(),
        'model' => '',
        'message' => 'Hello, can you help me with my order?',
        'sent_at' => Carbon::parse('2026-06-05 10:00:00'),
        'source' => 'widget',
    ], $overrides));
}

function makeOutputPayload(Llm $llm, ChatInputPayload $input, array $overrides = []): ChatOutputPayload
{
    return ChatOutputPayload::from(array_merge([
        'external_session_id' => $input->external_session_id,
        'external_conversation_id' => $input->external_conversation_id,
        'external_message_id' => (string) Str::uuid(),
        'message' => 'Sure — what is the order number?',
        'model' => $llm->external_name,
        'sent_at' => Carbon::parse('2026-06-05 10:00:01'),
        'generation_id' => 'gen_'.Str::random(12),
        'response_time_ms' => 1234,
        'source' => 'widget',
    ], $overrides));
}

beforeEach(function (): void {
    // Only fake the dispatched analytics event; do NOT blanket-fake() — that
    // also blocks Eloquent model lifecycle events (creating/saved/etc.) and
    // the HasExternalId concern stops auto-filling external_id, which breaks
    // every factory in the test.
    Event::fake([MessageSent::class]);
    Bus::fake();
});

it('persists a user message and dispatches MessageSent + AnalyzeMessageJob', function (): void {
    $agent = Agent::factory()->create();
    $action = makeStoreMessageAction(fakeLlmProvider());

    $payload = makeInputPayload($agent);

    $message = $action->execute($agent, $payload);

    expect($message)->toBeInstanceOf(Message::class)
        ->and($message->role)->toBe('user')
        ->and($message->content)->toBe($payload->message)
        ->and($message->external_id)->toBe($payload->external_message_id)
        ->and($message->llm_id)->toBeNull()
        ->and($message->cost_in_micro_cents)->toBeNull()
        ->and($message->prompt_tokens)->toBeNull()
        ->and($message->completion_tokens)->toBeNull();

    Event::assertDispatched(MessageSent::class, function (MessageSent $event) use ($agent, $payload): bool {
        return $event->agentId === $agent->id
            && $event->role === 'user'
            && $event->messageExternalId === $payload->external_message_id
            && $event->promptTokens === null
            && $event->completionTokens === null;
    });

    Bus::assertDispatched(AnalyzeMessageJob::class);
    Bus::assertNotDispatched(AnalyzeConversationJob::class); // need >= 5 messages
});

it('computes microcent cost from total_cost dollars (sub-cent precision boundary)', function (): void {
    // 0.00000123 dollars × 100_000_000 microcents/dollar = 123 microcents.
    // Real OpenRouter usage payloads return values at this scale, so the
    // dollarsToMicroCents conversion must not silently truncate.
    $agent = Agent::factory()->create();
    $llm = Llm::factory()->create(['external_name' => 'gpt-test-cost']);

    $provider = fakeLlmProvider(supportsCost: true, usage: [
        'tokens_prompt' => 42,
        'tokens_completion' => 17,
        'total_cost' => 0.00000123,
    ]);

    $input = makeInputPayload($agent);
    makeStoreMessageAction(fakeLlmProvider())->execute($agent, $input); // seed session/conversation

    $output = makeOutputPayload($llm, $input);
    $message = makeStoreMessageAction($provider)->execute($agent, $output);

    expect($message->role)->toBe('assistant')
        ->and($message->llm_id)->toBe($llm->id)
        ->and($message->prompt_tokens)->toBe(42)
        ->and($message->completion_tokens)->toBe(17)
        ->and($message->cost_in_micro_cents)->toBe(123)
        ->and($message->generation_id)->toBe($output->generation_id)
        ->and($message->response_time_ms)->toBe(1234);
});

it('rounds microcent cost half-up at the integer boundary', function (): void {
    // 0.00000123456 dollars × 100_000_000 = 123.456 → round to 123.
    // 0.00000123567 dollars × 100_000_000 = 123.567 → round to 124.
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

        $input = makeInputPayload($agent);
        makeStoreMessageAction(fakeLlmProvider())->execute($agent, $input);

        $output = makeOutputPayload($llm, $input);
        $message = makeStoreMessageAction($provider)->execute($agent, $output);

        expect($message->cost_in_micro_cents)->toBe($case['expected']);
    }
});

it('leaves cost+tokens null when the provider does not support cost tracking', function (): void {
    $agent = Agent::factory()->create();
    $llm = Llm::factory()->create(['external_name' => 'gpt-no-cost-tracking']);

    // supportsCost=false → getGenerationCost is never called even when a
    // generation_id is present on the payload. Stored cost/tokens are null.
    $provider = fakeLlmProvider(supportsCost: false);

    $input = makeInputPayload($agent);
    makeStoreMessageAction(fakeLlmProvider())->execute($agent, $input);

    $output = makeOutputPayload($llm, $input);
    $message = makeStoreMessageAction($provider)->execute($agent, $output);

    expect($message->role)->toBe('assistant')
        ->and($message->cost_in_micro_cents)->toBeNull()
        ->and($message->prompt_tokens)->toBeNull()
        ->and($message->completion_tokens)->toBeNull();
});

it('backfills the chatter onto a session that was minted without a user_id', function (): void {
    // Regression: on the hosted /chat surface TurnState mints the session row
    // up front with only agent_id (the authenticated chatter isn't known yet).
    // resolveSession must stamp the chatter onto that existing null-user row,
    // not key firstOrCreate on user_id (which would never match it).
    $agent = Agent::factory()->create();
    $chatter = User::factory()->chatter(User::factory()->workspace()->create())->create();

    $sessionExternalId = (string) Str::uuid();
    Session::create(['external_id' => $sessionExternalId, 'agent_id' => $agent->id]);

    makeStoreMessageAction(fakeLlmProvider())->execute($agent, makeInputPayload($agent, [
        'external_session_id' => $sessionExternalId,
        'user_id' => $chatter->id,
    ]));

    expect(Session::where('external_id', $sessionExternalId)->value('user_id'))->toBe($chatter->id);
});

it('dispatches the location event with the request-resolved country', function (): void {
    // The CF-aware country is captured on the payload at request time and must
    // reach the queued locate listener via the event — geo-locating the stored
    // IP there would mislabel CF-proxied visitors as the edge PoP (Bulgaria).
    Event::fake([MessageSent::class, SessionLocationUnknown::class]);

    $agent = Agent::factory()->create();

    makeStoreMessageAction(fakeLlmProvider())->execute($agent, makeInputPayload($agent, [
        'ip_address' => '203.0.113.10',
        'country' => 'iq',
    ]));

    Event::assertDispatched(
        SessionLocationUnknown::class,
        fn (SessionLocationUnknown $event): bool => $event->countryCode === 'iq',
    );
});

it('counts a chatter user message against their monthly limit', function (): void {
    $agent = Agent::factory()->create();
    $chatter = User::factory()->chatter(User::factory()->workspace()->create())->create();
    $allowance = app(ChatterMessageAllowance::class);
    $allowance->setLimit($chatter, 5);

    makeStoreMessageAction(fakeLlmProvider())->execute($agent, makeInputPayload($agent, [
        'user_id' => $chatter->id,
    ]));

    expect($allowance->usedThisPeriod($chatter))->toBe(1);
});

it('prefers inline response usage over the provider cost lookup', function (): void {
    // Inline usage (prompt/completion on the payload, captured from the stream)
    // is the authoritative billed count — it must win over getGenerationCost,
    // which should not even be consulted when inline usage is present.
    $agent = Agent::factory()->create();
    $llm = Llm::factory()->create(['external_name' => 'gpt-inline-usage']);

    // Provider WOULD return different numbers if asked — proving precedence.
    $provider = fakeLlmProvider(supportsCost: true, usage: [
        'tokens_prompt' => 999,
        'tokens_completion' => 999,
        'total_cost' => 1.23,
    ]);

    $input = makeInputPayload($agent);
    makeStoreMessageAction(fakeLlmProvider())->execute($agent, $input);

    $output = makeOutputPayload($llm, $input, [
        'prompt_tokens' => 1200,
        'completion_tokens' => 300,
    ]);
    $message = makeStoreMessageAction($provider)->execute($agent, $output);

    expect($message->prompt_tokens)->toBe(1200)
        ->and($message->completion_tokens)->toBe(300)
        // inline usage carries no dollar cost, so cost stays null on this path
        ->and($message->cost_in_micro_cents)->toBeNull();
});

it('logs the assistant turn tokens against the workspace owner billing cycle', function (): void {
    // Provider usage (prompt + completion) is the authoritative billed count;
    // StoreMessageAction adds it to the owner's current-cycle token usage.
    $agent = Agent::factory()->create();
    $owner = User::find($agent->user_id);
    $credit = $owner->creditSystem()->addCredit(
        ['messages_limit' => 4000, 'tokens_limit' => 1_000_000],
        now()->addMonth(),
    );

    $llm = Llm::factory()->create(['external_name' => 'gpt-token-log']);

    $input = makeInputPayload($agent);
    makeStoreMessageAction(fakeLlmProvider())->execute($agent, $input); // seed session/conversation

    $output = makeOutputPayload($llm, $input);
    makeStoreMessageAction(fakeLlmProvider(supportsCost: true, usage: [
        'tokens_prompt' => 120,
        'tokens_completion' => 80,
        'total_cost' => 0.0,
    ]))->execute($agent, $output);

    expect($credit->usage()->first()->tokens)->toBe(200);
});

it('does not dispatch AnalyzeMessageJob for an assistant turn', function (): void {
    // AnalyzeMessageJob::dispatchIf($isUserMessage, ...) — assistant turns
    // skip user-language/topic analysis since the LLM authored the text.
    $agent = Agent::factory()->create();
    $llm = Llm::factory()->create(['external_name' => 'gpt-skip-analyze']);

    $input = makeInputPayload($agent);
    makeStoreMessageAction(fakeLlmProvider())->execute($agent, $input);

    Bus::assertDispatchedTimes(AnalyzeMessageJob::class, 1); // from the user turn above
    Bus::fake([AnalyzeMessageJob::class, AnalyzeConversationJob::class]); // reset between assertions

    $output = makeOutputPayload($llm, $input);
    makeStoreMessageAction(fakeLlmProvider(supportsCost: true, usage: [
        'tokens_prompt' => 1,
        'tokens_completion' => 1,
        'total_cost' => 0.0,
    ]))->execute($agent, $output);

    Bus::assertNotDispatched(AnalyzeMessageJob::class);
});
