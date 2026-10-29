<?php

use Domain\Conversation\Models\Conversation;
use Domain\Conversation\Services\ChatLog\ChatLogWriter;
use Illuminate\Support\Facades\DB;

/**
 * The conversation stamp a stored message leaves moves the conversation in
 * the chat log at most once a minute, and finds the conversation with one
 * query.
 */
/** Puts the conversation's stamps at fixed times, without touching anything else. */
function clwStamp(Conversation $conversation, DateTimeInterface $lastActivityAt, DateTimeInterface $updatedAt): void
{
    DB::table('chat_conversations')->where('id', $conversation->id)
        ->update(['last_activity_at' => $lastActivityAt, 'updated_at' => $updatedAt]);
}

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
