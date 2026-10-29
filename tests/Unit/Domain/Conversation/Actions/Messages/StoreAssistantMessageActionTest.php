<?php

use Domain\Agent\Models\Agent;
use Domain\Analytics\Events\MessageSent;
use Domain\Conversation\Actions\Messages\StoreAssistantMessageAction;
use Domain\Conversation\Actions\Messages\StoreUserMessageAction;
use Domain\Conversation\Data\Messages\AssistantTurnData;
use Domain\Conversation\Data\Messages\UserTurnData;
use Domain\Conversation\Enums\ConversationSource;
use Domain\Conversation\Jobs\GenerateConversationTitleJob;
use Domain\Conversation\Models\Conversation;
use Domain\Conversation\Services\ChatLog\ChatLogWriter;
use Domain\Llm\Contracts\ChatCompletionProvider;
use Domain\Llm\Models\Llm;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Tests\Support\Llm\CannedLlmProvider;
use Tests\Support\Llm\FakeLlmProviders;

/**
 * Writing an assistant reply to the chat log: its token usage comes from the
 * stream when it sent one, else from the provider's lookup, and the
 * conversation title is queued at once on a channel, as a delayed fallback
 * where the browser asks for it, and not at all in the hosted chat.
 */
function samaAction(ChatCompletionProvider $provider): StoreAssistantMessageAction
{
    return new StoreAssistantMessageAction(new FakeLlmProviders($provider), app(ChatLogWriter::class));
}

/** A stored visitor message, so the reply has its session and conversation. */
function samaUserTurn(Agent $agent, ?string $source = 'widget'): UserTurnData
{
    $turn = UserTurnData::from([
        'external_session_id' => (string) Str::uuid7(),
        'external_conversation_id' => (string) Str::uuid7(),
        'external_message_id' => (string) Str::uuid7(),
        'llm_id' => null,
        'message' => 'Hello, can you help me with my order?',
        'sent_at' => Carbon::parse('2026-06-05 10:00:00'),
        'source' => $source,
    ]);

    app(StoreUserMessageAction::class)->execute($agent, $turn);

    return $turn;
}

function samaTurn(Llm $llm, UserTurnData $userTurn, array $overrides = []): AssistantTurnData
{
    return AssistantTurnData::from(array_merge([
        'external_session_id' => $userTurn->external_session_id,
        'external_conversation_id' => $userTurn->external_conversation_id,
        'external_message_id' => (string) Str::uuid7(),
        'message' => 'Sure — what is the order number?',
        'llm_id' => $llm->id,
        'sent_at' => Carbon::parse('2026-06-05 10:00:01'),
        'generation_id' => 'gen_'.Str::random(12),
        'trace_id' => (string) Str::uuid7(),
        'response_time_ms' => 1234,
        'source' => $userTurn->source,
    ], $overrides));
}

beforeEach(function (): void {
    Event::fake([MessageSent::class]);
    Bus::fake();
});

it('takes the token counts from the provider lookup, unless the stream already sent its usage', function (): void {
    $agent = Agent::factory()->create();
    $llm = Llm::factory()->create();
    $provider = CannedLlmProvider::withGeneration(['tokens_prompt' => 42, 'tokens_completion' => 17]);

    $looked = samaAction($provider)->execute($agent, samaTurn($llm, samaUserTurn($agent)));
    $inline = samaAction($provider)->execute($agent, samaTurn($llm, samaUserTurn($agent), [
        'prompt_tokens' => 1200,
        'completion_tokens' => 300,
    ]));

    expect($looked->prompt_tokens)->toBe(42)
        ->and($looked->completion_tokens)->toBe(17)
        ->and($inline->prompt_tokens)->toBe(1200)
        ->and($inline->completion_tokens)->toBe(300);
});

it('queues the conversation title by surface', function (ConversationSource $source, ?bool $delayed): void {
    $agent = Agent::factory()->create();
    $llm = Llm::factory()->create();
    $userTurn = samaUserTurn($agent);
    Conversation::query()->where('external_id', $userTurn->external_conversation_id)->update(['title' => null, 'source' => $source]);

    samaAction(new CannedLlmProvider)->execute($agent, samaTurn($llm, $userTurn));

    if ($delayed === null) {
        Bus::assertNotDispatched(GenerateConversationTitleJob::class);
    } else {
        Bus::assertDispatched(GenerateConversationTitleJob::class, fn (GenerateConversationTitleJob $job): bool => ($job->delay !== null) === $delayed);
    }
})->with([
    'a channel, at once' => [ConversationSource::Telegram, false],
    'the chat widget, as a delayed fallback' => [ConversationSource::Widget, true],
    'the hosted chat, left to the client' => [ConversationSource::HostedChat, null],
]);
