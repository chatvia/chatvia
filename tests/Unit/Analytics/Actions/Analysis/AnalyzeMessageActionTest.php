<?php

use Domain\Agent\Models\Agent;
use Domain\Analytics\Actions\Analysis\AnalyzeMessageAction;
use Domain\Analytics\Enums\Sentiment;
use Domain\Analytics\Enums\Tone;
use Domain\Analytics\Events\MessageAnalyzed;
use Domain\Conversation\Models\Conversation;
use Domain\Conversation\Models\Message;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;

/**
 * AnalyzeMessageAction is the per-message branch of analytics (the per-
 * conversation branch is AnalyzeConversationAction). It must:
 *  - call ConversationAnalyzer::analyzeMessage for a user message with no
 *    sentiment+tone yet, on an agent with at least one dimension switched on,
 *  - skip non-user messages and already-analysed ones,
 *  - skip when neither sentiment nor tone is wanted,
 *  - dispatch MessageAnalyzed with only the wanted dimensions populated.
 */
function amaMakeMessage(Agent $agent, string $role = 'user'): Message
{
    $factory = $role === 'user' ? Message::factory()->fromUser() : Message::factory()->fromAssistant();

    return $factory->create([
        'chat_conversation_id' => Conversation::factory()->create(['agent_id' => $agent->id])->id,
        'content' => 'I am really frustrated with my invoice this month.',
    ]);
}

/**
 * Sentiment + tone are opt-in (the `analytics` settings group defaults them to
 * false — see config/model-settings.php), so an agent that should be analyzed
 * needs the dimensions switched on explicitly.
 */
function amaEnableAnalytics(Agent $agent, bool $sentiment = true, bool $tone = true): void
{
    $agent->setSetting('sentiment', $sentiment, 'analytics');
    $agent->setSetting('tone', $tone, 'analytics');
}

it('analyzes a user message, persists sentiment+tone, and dispatches MessageAnalyzed', function (): void {
    Event::fake([MessageAnalyzed::class]);

    $agent = Agent::factory()->create();
    amaEnableAnalytics($agent);
    $message = amaMakeMessage($agent);

    fakeLlmCompletion(json_encode([
        'sentiment' => 'negative',
        'tone' => 'frustrated',
    ]));

    app(AnalyzeMessageAction::class)->execute($message->external_id);

    $message->refresh();
    expect($message->sentiment)->toBe(Sentiment::Negative->value)
        ->and($message->tone)->toBe(Tone::Frustrated->value);

    Event::assertDispatched(MessageAnalyzed::class, fn (MessageAnalyzed $event): bool => $event->agentId === $agent->id
        && $event->sentiment === Sentiment::Negative->value
        && $event->tone === Tone::Frustrated->value);
});

it('skips assistant messages without calling the LLM', function (): void {
    Event::fake([MessageAnalyzed::class]);
    Http::fake();

    $agent = Agent::factory()->create();
    amaEnableAnalytics($agent);
    $message = amaMakeMessage($agent, role: 'assistant');

    app(AnalyzeMessageAction::class)->execute($message->external_id);

    $message->refresh();
    expect($message->sentiment)->toBeNull()
        ->and($message->tone)->toBeNull();
    Http::assertNothingSent();
    Event::assertNotDispatched(MessageAnalyzed::class);
});

it('is idempotent: skips a message that already has sentiment AND tone', function (): void {
    Event::fake([MessageAnalyzed::class]);
    Http::fake();

    $agent = Agent::factory()->create();
    amaEnableAnalytics($agent);
    $message = amaMakeMessage($agent);
    $message->update([
        'sentiment' => Sentiment::Positive->value,
        'tone' => Tone::Satisfied->value,
    ]);

    app(AnalyzeMessageAction::class)->execute($message->external_id);

    $message->refresh();
    expect($message->sentiment)->toBe(Sentiment::Positive->value)
        ->and($message->tone)->toBe(Tone::Satisfied->value);
    Http::assertNothingSent();
    Event::assertNotDispatched(MessageAnalyzed::class);
});

it('makes no LLM call when the agent wants neither sentiment nor tone', function (): void {
    Event::fake([MessageAnalyzed::class]);
    Http::fake();

    $agent = Agent::factory()->create();
    amaEnableAnalytics($agent, sentiment: false, tone: false);
    $message = amaMakeMessage($agent);

    app(AnalyzeMessageAction::class)->execute($message->external_id);

    expect($message->refresh()->sentiment)->toBeNull()
        ->and($message->tone)->toBeNull();
    Http::assertNothingSent();
    Event::assertNotDispatched(MessageAnalyzed::class);
});

it('stores both dimensions but rolls up only the wanted one', function (bool $sentiment, bool $tone): void {
    // The one call returns both, so both are saved (the idempotency guard needs
    // both); the event carries null for the dimension the agent switched off,
    // which keeps it out of the daily rollup.
    Event::fake([MessageAnalyzed::class]);

    $agent = Agent::factory()->create();
    amaEnableAnalytics($agent, sentiment: $sentiment, tone: $tone);
    $message = amaMakeMessage($agent);

    fakeLlmCompletion(json_encode(['sentiment' => 'negative', 'tone' => 'frustrated']));

    app(AnalyzeMessageAction::class)->execute($message->external_id);

    expect($message->refresh()->sentiment)->toBe(Sentiment::Negative->value)
        ->and($message->tone)->toBe(Tone::Frustrated->value);

    Event::assertDispatched(MessageAnalyzed::class, fn (MessageAnalyzed $event): bool => $event->agentId === $agent->id
        && $event->sentiment === ($sentiment ? Sentiment::Negative->value : null)
        && $event->tone === ($tone ? Tone::Frustrated->value : null));
})->with([
    'sentiment only' => [true, false],
    'tone only' => [false, true],
]);

it('saves nothing and rolls nothing up on a malformed analyzer response', function (): void {
    // A guessed NEUTRAL/NEUTRAL would be counted in the daily rollups as if the
    // model had said so, and the idempotency guard would then block any retry.
    Event::fake([MessageAnalyzed::class]);

    $agent = Agent::factory()->create();
    amaEnableAnalytics($agent);
    $message = amaMakeMessage($agent);

    fakeLlmCompletion('not json');

    app(AnalyzeMessageAction::class)->execute($message->external_id);

    $message->refresh();
    expect($message->sentiment)->toBeNull()
        ->and($message->tone)->toBeNull();
    Event::assertNotDispatched(MessageAnalyzed::class);
});
