<?php

use App\Identity\Chatter\Models\Chatter;
use Domain\Agent\Models\Agent;
use Domain\Conversation\Actions\Conversations\CreateApiConversationAction;
use Domain\Conversation\Data\Conversations\CreateApiConversationData;
use Domain\Conversation\Enums\ConversationSource;

/*
|--------------------------------------------------------------------------
| CreateApiConversationAction
|--------------------------------------------------------------------------
|
| Opens the session + conversation pair an API chat runs on: a fresh session
| per conversation, the `api` surface, and the optional chatter stamped on
| the session so their limit and identity follow the turn.
*/

it('opens a fresh session and a hidden api conversation', function (): void {
    $agent = Agent::factory()->create();

    $conversation = app(CreateApiConversationAction::class)->execute(
        new CreateApiConversationData(agent: $agent, title: 'Orders bot'),
    );

    expect($conversation->source)->toBe(ConversationSource::Api)
        ->and($conversation->title)->toBe('Orders bot')
        ->and($conversation->is_visible)->toBeFalse()
        ->and($conversation->workspace_id)->toBe($agent->workspace_id)
        ->and($conversation->session->agent_id)->toBe($agent->id)
        ->and($conversation->session->user_id)->toBeNull()
        ->and($conversation->session->chatter_id)->toBeNull();
});

it('stamps the chatter on the session when one is attributed', function (): void {
    $agent = Agent::factory()->create();
    $chatter = Chatter::factory()->create(['workspace_id' => $agent->workspace_id]);

    $conversation = app(CreateApiConversationAction::class)->execute(
        new CreateApiConversationData(agent: $agent, chatter: $chatter),
    );

    expect($conversation->session->chatter_id)->toBe($chatter->id)
        ->and($conversation->session->user_id)->toBeNull();
});

it('gives every conversation its own session', function (): void {
    $agent = Agent::factory()->create();
    $action = app(CreateApiConversationAction::class);

    $first = $action->execute(new CreateApiConversationData(agent: $agent));
    $second = $action->execute(new CreateApiConversationData(agent: $agent));

    expect($first->chat_session_id)->not->toBe($second->chat_session_id);
});
