<?php

use Domain\Agent\Models\Agent;
use Domain\Conversation\Models\Conversation;
use Domain\Conversation\Models\Session;
use Domain\Widget\Data\Auth\WidgetBroadcastAuthData;
use Domain\Widget\Enums\BroadcastDenialReason;
use Domain\Widget\Services\BroadcastAuthorizer;

/**
 * Widget visitors join a conversation's presence channel by session
 * ownership: the channel must be `presence-chat.<conversation id>`, the
 * conversation must belong to the visitor's session, and the session to the
 * agent the widget is for.
 */
function widgetConversation(Agent $agent): Conversation
{
    $session = Session::factory()->create(['agent_id' => $agent->id]);

    return Conversation::factory()->create(['chat_session_id' => $session->id, 'agent_id' => $agent->id]);
}

it('signs the presence channel of a conversation in the visitor session', function (): void {
    $agent = Agent::factory()->create();
    $conversation = widgetConversation($agent);
    $sessionId = $conversation->session->external_id;

    $result = app(BroadcastAuthorizer::class)->authorize(
        'presence-chat.'.$conversation->external_id,
        $sessionId,
        '1234.5678',
        $agent,
    );

    expect($result)->toBeInstanceOf(WidgetBroadcastAuthData::class)
        ->and($result->auth)->toStartWith(config('broadcasting.connections.reverb.key').':')
        ->and(json_decode($result->channel_data, true))->toMatchArray([
            'user_id' => $sessionId,
            'user_info' => ['name' => 'Visitor', 'type' => 'user', 'session_id' => $sessionId],
        ]);
});

it('refuses a channel that is not a conversation presence channel', function (): void {
    $agent = Agent::factory()->create();
    $conversation = widgetConversation($agent);

    expect(app(BroadcastAuthorizer::class)->authorize(
        'private-chat.'.$conversation->external_id,
        $conversation->session->external_id,
        '1234.5678',
        $agent,
    ))->toBe(BroadcastDenialReason::InvalidChannel);
});

it('refuses a conversation from another session', function (): void {
    $agent = Agent::factory()->create();
    $conversation = widgetConversation($agent);
    $otherSession = Session::factory()->create(['agent_id' => $agent->id]);

    expect(app(BroadcastAuthorizer::class)->authorize(
        'presence-chat.'.$conversation->external_id,
        $otherSession->external_id,
        '1234.5678',
        $agent,
    ))->toBe(BroadcastDenialReason::UnknownConversation);
});

it('refuses a conversation through another agent\'s widget', function (): void {
    $conversation = widgetConversation(Agent::factory()->create());

    expect(app(BroadcastAuthorizer::class)->authorize(
        'presence-chat.'.$conversation->external_id,
        $conversation->session->external_id,
        '1234.5678',
        Agent::factory()->create(),
    ))->toBe(BroadcastDenialReason::UnknownConversation);
});
