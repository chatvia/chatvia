<?php

use Domain\Agent\Models\Agent;
use Domain\Conversation\Enums\ConversationSource;
use Domain\Conversation\Models\Conversation;
use Domain\Conversation\Models\Session;
use Domain\Conversation\Topic\Models\Topic;

/**
 * filterByTopic now matches through the conversation_topics pivot, so a
 * multi-label conversation appears under each of its topics.
 */
it('matches conversations carrying the requested topic, multi-label aware', function (): void {
    $agent = Agent::factory()->create();
    $session = Session::factory()->create(['agent_id' => $agent->id]);

    $billing = Topic::create(['user_id' => $agent->user_id, 'topic' => 'Billing', 'description' => 'x']);
    $bug = Topic::create(['user_id' => $agent->user_id, 'topic' => 'Bug', 'description' => 'y']);

    $multi = Conversation::factory()->create(['chat_session_id' => $session->id, 'source' => ConversationSource::Widget]);
    $multi->topics()->attach([$billing->id, $bug->id]);

    $single = Conversation::factory()->create(['chat_session_id' => $session->id, 'source' => ConversationSource::Widget]);
    $single->topics()->attach([$bug->id]);

    $billingMatches = Conversation::query()->filterByTopic((string) $billing->id)->pluck('id')->all();
    expect($billingMatches)->toContain($multi->id)
        ->and($billingMatches)->not->toContain($single->id);

    $bugMatches = Conversation::query()->filterByTopic((string) $bug->id)->pluck('id')->all();
    expect($bugMatches)->toContain($multi->id)
        ->and($bugMatches)->toContain($single->id);
});
