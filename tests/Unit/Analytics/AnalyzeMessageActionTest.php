<?php

use Domain\Agent\Models\Agent;
use Domain\Analytics\Actions\AnalyzeMessageAction;
use Domain\Analytics\Enums\SentimentEnum;
use Domain\Analytics\Enums\ToneEnum;
use Domain\Analytics\Events\MessageAnalyzed;
use Domain\Conversation\Models\Conversation;
use Domain\Conversation\Models\Message;
use Domain\Conversation\Models\Session;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;

/**
 * AnalyzeMessageAction is the per-message branch of analytics (the per-
 * conversation branch uses ConversationAnalyzer::analyze directly via
 * AnalyzeConversationAction). It must:
 *  - call ConversationAnalyzer::analyzeMessage for user messages with no
 *    sentiment+tone yet, on an agent whose team has the capability AND has
 *    the dimension toggled on,
 *  - skip non-user messages and already-analysed ones,
 *  - skip when neither sentiment nor tone is wanted,
 *  - dispatch MessageAnalyzed with only the wanted dimensions populated.
 */
function amaMakeMessage(Agent $agent, string $role = 'user'): Message
{
    // Conversation links to its agent via the session (chat_session_id);
    // there is no agent_id column on chat_conversations.
    $session = Session::factory()->create(['agent_id' => $agent->id]);
    $conversation = Conversation::factory()->create(['chat_session_id' => $session->id]);

    return Message::create([
        'external_id' => (string) Str::uuid(),
        'chat_conversation_id' => $conversation->id,
        'role' => $role,
        'content' => 'I am really frustrated with my invoice this month.',
    ]);
}

it('analyzes a user message, persists sentiment+tone, and dispatches MessageAnalyzed', function (): void {
    Event::fake([MessageAnalyzed::class]);

    $agent = proPlanAgent();
    $message = amaMakeMessage($agent);

    fakeLlmCompletion(json_encode([
        'sentiment' => 'negative',
        'tone' => 'frustrated',
    ]));

    app(AnalyzeMessageAction::class)->execute($message->external_id);

    $message->refresh();
    expect($message->sentiment)->toBe(SentimentEnum::NEGATIVE->value)
        ->and($message->tone)->toBe(ToneEnum::FRUSTRATED->value);

    Event::assertDispatched(MessageAnalyzed::class, function (MessageAnalyzed $event) use ($agent): bool {
        return $event->agentId === $agent->id
            && $event->sentiment === SentimentEnum::NEGATIVE->value
            && $event->tone === ToneEnum::FRUSTRATED->value;
    });
});

it('skips assistant messages without calling the LLM', function (): void {
    Event::fake([MessageAnalyzed::class]);

    $agent = proPlanAgent();
    $message = amaMakeMessage($agent, role: 'assistant');

    // No HTTP fake configured — if analyzeMessage were called the HTTP layer
    // would explode.
    app(AnalyzeMessageAction::class)->execute($message->external_id);

    $message->refresh();
    expect($message->sentiment)->toBeNull()
        ->and($message->tone)->toBeNull();
    Event::assertNotDispatched(MessageAnalyzed::class);
});

it('is idempotent: skips a message that already has sentiment AND tone', function (): void {
    Event::fake([MessageAnalyzed::class]);

    $agent = proPlanAgent();
    $message = amaMakeMessage($agent);
    $message->update([
        'sentiment' => SentimentEnum::POSITIVE->value,
        'tone' => ToneEnum::SATISFIED->value,
    ]);

    app(AnalyzeMessageAction::class)->execute($message->external_id);

    $message->refresh();
    expect($message->sentiment)->toBe(SentimentEnum::POSITIVE->value)
        ->and($message->tone)->toBe(ToneEnum::SATISFIED->value);
    Event::assertNotDispatched(MessageAnalyzed::class);
});

it('returns NEUTRAL/NEUTRAL on a malformed analyzer response without throwing', function (): void {
    Event::fake([MessageAnalyzed::class]);

    $agent = proPlanAgent();
    $message = amaMakeMessage($agent);

    fakeLlmCompletion('not json');

    app(AnalyzeMessageAction::class)->execute($message->external_id);

    $message->refresh();
    expect($message->sentiment)->toBe(SentimentEnum::NEUTRAL->value)
        ->and($message->tone)->toBe(ToneEnum::NEUTRAL->value);
});
