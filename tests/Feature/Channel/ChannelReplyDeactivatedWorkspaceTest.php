<?php

use Domain\Agent\Models\Agent;
use Domain\Channel\Data\ChannelTurnData;
use Domain\Channel\Services\ChannelReplyRunner;
use Domain\Conversation\Enums\ConversationSource;
use Domain\Conversation\Models\Message;
use Domain\Llm\Models\Llm;
use Illuminate\Support\Str;

/**
 * Messaging channels mirror the web chat's stop for a deactivated workspace:
 * the runner answers with its fixed "unavailable" reply and never runs the
 * chat pipeline, so nothing is stored or counted.
 */
it('answers with the unavailable reply when the workspace is deactivated', function (): void {
    $agent = Agent::factory()->create(['llm_id' => Llm::factory()->create()->id, 'is_public' => true]);
    deactivateWorkspaceOf($agent);
    $messagesBefore = Message::query()->count();

    $reply = app(ChannelReplyRunner::class)->reply(new ChannelTurnData(
        agent: $agent,
        sessionExternalId: (string) Str::uuid(),
        conversationExternalId: (string) Str::uuid(),
        message: 'hello',
        source: ConversationSource::Whatsapp,
        requestIdPrefix: 'wa_',
    ));

    expect($reply)->toBe('Sorry — this assistant is unavailable right now. Please try again later.')
        ->and(Message::query()->count())->toBe($messagesBefore);
});
