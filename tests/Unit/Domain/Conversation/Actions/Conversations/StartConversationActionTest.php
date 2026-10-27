<?php

use Domain\Agent\Models\Agent;
use Domain\Conversation\Actions\Conversations\StartConversationAction;
use Domain\Conversation\Data\Memory\ChatScopeData;
use Domain\Conversation\Enums\ConversationSource;
use Domain\Conversation\Models\Conversation;
use Illuminate\Support\Str;

/**
 * A started conversation is hidden until it is used. Most are never used (the
 * widget starts one on every visit), so it gets a short life, not the full
 * retention window.
 */
it('starts a hidden conversation that expires after a day', function (): void {
    config(['chatvia.retention.conversations_days' => 90]);
    $agent = Agent::factory()->create();

    $conversation = app(StartConversationAction::class)->execute(new ChatScopeData($agent, (string) Str::uuid7()), ConversationSource::Widget);

    expect($conversation->is_visible)->toBeFalse()
        ->and($conversation->source)->toBe(ConversationSource::Widget)
        ->and($conversation->expires_at->between(now()->addDay()->subMinute(), now()->addDay()->addMinute()))->toBeTrue();
});
