<?php

use Domain\Agent\Models\Agent;
use Domain\Conversation\Actions\Activity\TouchConversationActivityAction;
use Domain\Conversation\Models\Conversation;
use Domain\Conversation\Models\Session;
use Domain\Conversation\Services\ChatLog\ChatLogWriter;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * The conversation stamp a stored message leaves: it moves the conversation
 * in the chat log (updated_at) at most once a minute, even right after a
 * widget heartbeat refreshed last_activity_at, and finds the conversation
 * with one query. (The session's country reaching analytics is the store
 * action's.)
 */

/** Puts the conversation's stamps at fixed times, without touching anything else. */
function clwStamp(Conversation $conversation, DateTimeInterface $lastActivityAt, DateTimeInterface $updatedAt): void
{
    DB::table('chat_conversations')->where('id', $conversation->id)
        ->update(['last_activity_at' => $lastActivityAt, 'updated_at' => $updatedAt]);
}

it('moves the conversation in the chat log even right after a heartbeat', function (): void {
    $this->freezeSecond();
    $conversation = Conversation::factory()->visible()->create();
    clwStamp($conversation, now()->subHour(), now()->subHour());
    // The heartbeat refreshes last_activity_at only.
    app(TouchConversationActivityAction::class)->execute($conversation);

    app(ChatLogWriter::class)->conversation($conversation->session, $conversation->external_id, 'widget');

    expect($conversation->fresh()->updated_at->equalTo(now()))->toBeTrue();
});

it('moves the activity time when only it is old', function (): void {
    $this->freezeSecond();
    $conversation = Conversation::factory()->visible()->create();
    clwStamp($conversation, now()->subHour(), now()->subSeconds(5));

    app(ChatLogWriter::class)->conversation($conversation->session, $conversation->external_id, 'widget');

    expect($conversation->fresh()->last_activity_at->equalTo(now()))->toBeTrue();
});

it('leaves both stamps alone within a minute and reads the conversation once', function (): void {
    $conversation = Conversation::factory()->visible()->create();
    $recent = now()->subSeconds(10)->startOfSecond();
    clwStamp($conversation, $recent, $recent);
    $session = $conversation->session;
    $queries = 0;
    DB::listen(function () use (&$queries): void {
        $queries++;
    });

    app(ChatLogWriter::class)->conversation($session, $conversation->external_id, 'widget');
    $sent = $queries;

    $fresh = $conversation->fresh();
    expect($sent)->toBe(1)
        ->and($fresh->updated_at->equalTo($recent))->toBeTrue()
        ->and($fresh->last_activity_at->equalTo($recent))->toBeTrue();
});

it('creates the conversation when it does not exist yet', function (): void {
    $agent = Agent::factory()->create();
    $session = Session::factory()->create(['agent_id' => $agent->id]);
    $id = (string) Str::uuid();

    $conversation = app(ChatLogWriter::class)->conversation($session, $id, 'widget');

    expect($conversation->fresh())
        ->external_id->toBe($id)
        ->is_visible->toBeTrue()
        ->last_activity_at->not->toBeNull();
});
