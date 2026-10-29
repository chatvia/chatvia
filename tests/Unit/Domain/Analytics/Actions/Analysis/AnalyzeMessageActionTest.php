<?php

use Domain\Agent\Models\Agent;
use Domain\Analytics\Actions\Analysis\AnalyzeMessageAction;
use Domain\Analytics\Enums\Sentiment;
use Domain\Analytics\Enums\Tone;
use Domain\Analytics\Events\MessageAnalyzed;
use Domain\Conversation\Models\Conversation;
use Domain\Conversation\Models\Message;
use Illuminate\Support\Facades\Event;

/**
 * AnalyzeMessageAction is the per-message branch of analytics (the per-
 * conversation branch is AnalyzeConversationAction): a visitor message with
 * no sentiment and tone yet is analysed once and announced as MessageAnalyzed;
 * an analysed one is skipped.
 */
function amaMakeMessage(Agent $agent): Message
{
    return Message::factory()->fromUser()->create([
        'chat_conversation_id' => Conversation::factory()->create(['agent_id' => $agent->id])->id,
        'content' => 'I am really frustrated with my invoice this month.',
    ]);
}

/**
 * Sentiment + tone are opt-in (the `analytics` settings group defaults them to
 * false — see config/model-settings.php), so an agent that should be analyzed
 * needs the dimensions switched on explicitly.
 */
function amaEnableAnalytics(Agent $agent): void
{
    $agent->setSetting('sentiment', true, 'analytics');
    $agent->setSetting('tone', true, 'analytics');
}

it('analyzes a visitor message once and announces the result', function (): void {
    Event::fake([MessageAnalyzed::class]);
    $agent = Agent::factory()->create();
    amaEnableAnalytics($agent);
    $message = amaMakeMessage($agent);
    fakeLlmCompletion(json_encode([
        'sentiment' => 'negative',
        'tone' => 'frustrated',
    ]));

    app(AnalyzeMessageAction::class)->execute($message->external_id);
    app(AnalyzeMessageAction::class)->execute($message->external_id);

    expect($message->refresh()->sentiment)->toBe(Sentiment::Negative)
        ->and($message->tone)->toBe(Tone::Frustrated);
    Event::assertDispatchedTimes(MessageAnalyzed::class, 1);
    Event::assertDispatched(MessageAnalyzed::class, fn (MessageAnalyzed $event): bool => $event->agentId === $agent->id
        && $event->sentiment === Sentiment::Negative->value
        && $event->tone === Tone::Frustrated->value);
});
