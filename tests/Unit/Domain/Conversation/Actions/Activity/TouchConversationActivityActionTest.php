<?php

use Domain\Conversation\Actions\Activity\TouchConversationActivityAction;
use Domain\Conversation\Models\Conversation;
use Illuminate\Support\Facades\DB;

/**
 * The widget heartbeat stamp: moves last_activity_at forward without touching
 * updated_at, skips hidden conversations, and moves at most once a minute.
 */
it('stamps a visible conversation without touching updated_at', function (): void {
    $this->freezeSecond();
    $conversation = Conversation::factory()->create(['is_visible' => true, 'last_activity_at' => null]);
    // An hour back, so a touch now would show: the column keeps whole seconds.
    $updatedAt = now()->subHour()->startOfSecond();
    DB::table('chat_conversations')->where('id', $conversation->id)->update(['updated_at' => $updatedAt]);

    app(TouchConversationActivityAction::class)->execute($conversation);

    $fresh = $conversation->fresh();
    expect($fresh->last_activity_at->equalTo(now()))->toBeTrue()
        ->and($fresh->updated_at->equalTo($updatedAt))->toBeTrue();
});

it('skips a hidden conversation', function (): void {
    $conversation = Conversation::factory()->create(['is_visible' => false, 'last_activity_at' => null]);

    app(TouchConversationActivityAction::class)->execute($conversation);

    expect($conversation->fresh()->last_activity_at)->toBeNull();
});

it('moves the stamp at most once a minute', function (): void {
    $recent = now()->subSeconds(10);
    $conversation = Conversation::factory()->create(['is_visible' => true, 'last_activity_at' => $recent]);

    app(TouchConversationActivityAction::class)->execute($conversation);

    expect($conversation->fresh()->last_activity_at->timestamp)->toBe($recent->timestamp);
});
