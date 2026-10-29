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
function baConversation(Agent $agent): Conversation
{
    $session = Session::factory()->create(['agent_id' => $agent->id]);

    return Conversation::factory()->create(['chat_session_id' => $session->id, 'agent_id' => $agent->id]);
}

it('signs the presence channel of a conversation in the visitor session', function (): void {
    $agent = Agent::factory()->create();
    $conversation = baConversation($agent);
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

it('refuses another channel, a conversation of another session or one through another agent\'s widget', function (Closure $request, BroadcastDenialReason $reason): void {
    $agent = Agent::factory()->create();
    $conversation = baConversation($agent);
    [$channel, $sessionId, $widgetAgent] = $request($conversation, $agent);

    expect(app(BroadcastAuthorizer::class)->authorize($channel, $sessionId, '1234.5678', $widgetAgent))->toBe($reason);
})->with([
    'not a conversation presence channel' => [fn (Conversation $conversation, Agent $agent): array => ['private-chat.'.$conversation->external_id, $conversation->session->external_id, $agent], BroadcastDenialReason::InvalidChannel],
    'a conversation of another session' => [fn (Conversation $conversation, Agent $agent): array => ['presence-chat.'.$conversation->external_id, Session::factory()->create(['agent_id' => $agent->id])->external_id, $agent], BroadcastDenialReason::UnknownConversation],
    'another agent\'s widget' => [fn (Conversation $conversation): array => ['presence-chat.'.$conversation->external_id, $conversation->session->external_id, Agent::factory()->create()], BroadcastDenialReason::UnknownConversation],
]);
