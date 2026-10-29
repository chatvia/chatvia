<?php

use Domain\Agent\Models\Agent;
use Domain\Conversation\Data\Media\MediaChannelAuthData;
use Domain\Conversation\Models\Conversation;
use Domain\Conversation\Models\Session;
use Domain\Conversation\Services\Realtime\MediaChannelAuthorizer;
use Illuminate\Support\Str;

/**
 * A hosted-chat visitor may listen on the media channel of a conversation of
 * their own session with this agent, and on nothing else.
 */
function mcaConversation(Agent $agent): Conversation
{
    $session = Session::factory()->create(['agent_id' => $agent->id]);

    return Conversation::factory()->create(['chat_session_id' => $session->id, 'agent_id' => $agent->id]);
}

it('signs the media channel of a conversation in the visitor session', function (): void {
    $agent = Agent::factory()->create();
    $conversation = mcaConversation($agent);

    $result = app(MediaChannelAuthorizer::class)->authorize($agent, $conversation->session->external_id, 'private-chat-media.'.$conversation->external_id, '1234.5678');

    expect($result)->toBeInstanceOf(MediaChannelAuthData::class)
        ->and($result->auth)->toStartWith(config('broadcasting.connections.reverb.key').':');
});

it('refuses a conversation of another session, and the session through another agent', function (): void {
    $agent = Agent::factory()->create();
    $mine = mcaConversation($agent);
    $theirs = mcaConversation($agent);
    $authorizer = app(MediaChannelAuthorizer::class);

    expect($authorizer->authorize($agent, $mine->session->external_id, 'private-chat-media.'.$theirs->external_id, '1234.5678'))->toBeNull()
        ->and($authorizer->authorize(Agent::factory()->create(), $mine->session->external_id, 'private-chat-media.'.$mine->external_id, '1234.5678'))->toBeNull();
});

it('refuses any other channel', function (string $channel): void {
    $agent = Agent::factory()->create();
    $conversation = mcaConversation($agent);

    expect(app(MediaChannelAuthorizer::class)->authorize($agent, $conversation->session->external_id, Str::replace('{id}', $conversation->external_id, $channel), '1234.5678'))->toBeNull();
})->with([
    'presence channel' => 'presence-chat.{id}',
    'no prefix' => 'chat-media.{id}',
    'another private channel' => 'private-App.Identity.Workspace.Models.User.1',
    'not a uuid' => 'private-chat-media.{id}x',
]);
