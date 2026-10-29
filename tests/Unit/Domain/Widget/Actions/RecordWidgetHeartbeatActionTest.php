<?php

use Domain\Agent\Models\Agent;
use Domain\Conversation\Models\Conversation;
use Domain\Conversation\Models\Session;
use Domain\Widget\Actions\RecordWidgetHeartbeatAction;
use Illuminate\Support\Str;

/**
 * The widget heartbeat stamps the visitor's conversation. A conversation that
 * is not the visitor's is refused on every ping, and ids that are not UUIDs
 * before the cache.
 */
function rwhConversation(): Conversation
{
    $agent = Agent::factory()->create();

    return Conversation::factory()->create([
        'chat_session_id' => Session::factory()->create(['agent_id' => $agent->id])->id,
        'is_visible' => true,
        'last_activity_at' => now()->subHour(),
    ]);
}

/** One ping for the conversation, from its own session unless another is named. */
function rwhPing(Conversation $conversation, ?Session $session = null): bool
{
    return app(RecordWidgetHeartbeatAction::class)->execute(
        $conversation->agent,
        $conversation->external_id,
        ($session ?? $conversation->session)->external_id,
    );
}

it('stamps the conversation on the first ping', function (): void {
    $conversation = rwhConversation();

    expect(rwhPing($conversation))->toBeTrue()
        ->and($conversation->fresh()->last_activity_at->greaterThan(now()->subMinute()))->toBeTrue();
});

it('refuses a conversation of another session on every ping, and ids that are not UUIDs', function (): void {
    $conversation = rwhConversation();
    $otherSession = Session::factory()->create(['agent_id' => $conversation->agent_id]);

    expect(rwhPing($conversation, $otherSession))->toBeFalse()
        ->and(rwhPing($conversation, $otherSession))->toBeFalse()
        ->and(app(RecordWidgetHeartbeatAction::class)->execute($conversation->agent, Str::repeat('x', 500), (string) Str::uuid7()))->toBeFalse()
        ->and($conversation->fresh()->last_activity_at->lessThan(now()->subMinutes(30)))->toBeTrue();
});
