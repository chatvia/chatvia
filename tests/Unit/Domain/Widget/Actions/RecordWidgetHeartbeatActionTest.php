<?php

use Domain\Agent\Models\Agent;
use Domain\Conversation\Models\Conversation;
use Domain\Conversation\Models\Session;
use Domain\Widget\Actions\RecordWidgetHeartbeatAction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * The widget heartbeat: one ping a minute per visitor and conversation
 * reaches the database; the others return without a query. A conversation
 * that is not the visitor's is refused on every ping.
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

it('answers a second ping within a minute without a query', function (): void {
    $conversation = rwhConversation();
    $conversation->load(['agent', 'session']);
    rwhPing($conversation);

    $queries = [];
    DB::listen(function ($query) use (&$queries): void {
        $queries[] = $query->sql;
    });

    expect(rwhPing($conversation))->toBeTrue()
        ->and($queries)->toBe([]);
});

it('checks again once the minute is over', function (): void {
    $conversation = rwhConversation();
    rwhPing($conversation);

    $this->travel(RecordWidgetHeartbeatAction::GATE_SECONDS + 30)->seconds();
    rwhPing($conversation);

    expect($conversation->fresh()->last_activity_at->greaterThan(now()->subSeconds(5)))->toBeTrue();
});

it('refuses a conversation of another session on every ping', function (): void {
    $conversation = rwhConversation();
    $otherSession = Session::factory()->create(['agent_id' => $conversation->agent_id]);

    expect(rwhPing($conversation, $otherSession))->toBeFalse()
        ->and(rwhPing($conversation, $otherSession))->toBeFalse()
        ->and($conversation->fresh()->last_activity_at->lessThan(now()->subMinutes(30)))->toBeTrue();
});

it('refuses ids that are not UUIDs before the cache', function (): void {
    $conversation = rwhConversation();

    expect(app(RecordWidgetHeartbeatAction::class)->execute($conversation->agent, Str::repeat('x', 500), (string) Str::uuid()))->toBeFalse();
});
