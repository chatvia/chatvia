<?php

use Domain\Conversation\Actions\Activity\TouchConversationActivityAction;
use Domain\Conversation\Models\Conversation;

/**
 * The widget heartbeat stamp on a conversation moves at most once a minute.
 */
it('leaves a stamp younger than a minute unchanged', function (): void {
    $recent = now()->subSeconds(10);
    $conversation = Conversation::factory()->create(['is_visible' => true, 'last_activity_at' => $recent]);

    app(TouchConversationActivityAction::class)->execute($conversation);

    expect($conversation->fresh()->last_activity_at->timestamp)->toBe($recent->timestamp);
});
