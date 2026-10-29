<?php

use Domain\Agent\Models\Agent;
use Domain\Conversation\Data\Turn\StreamRequestData;
use Domain\Conversation\Data\Turn\StreamRequestMessageData;
use Domain\Conversation\Models\Conversation;
use Domain\Conversation\Services\Tooling\ToolCallFactory;
use Domain\Llm\Models\Llm;
use Illuminate\Support\Str;

/**
 * The call a tool receives names the conversation only when that
 * conversation is the turn's own (its session, its agent): the id comes from
 * the client, and a receiver may scope its writes by it.
 */
function tcfyRequest(Agent $agent, string $sessionId, string $conversationId, ?string $chatterExternalId = null): StreamRequestData
{
    return new StreamRequestData(
        agent: $agent,
        message: new StreamRequestMessageData(model: Llm::factory()->create(), message: 'Hi'),
        externalSessionId: $sessionId,
        conversationId: $conversationId,
        chatterExternalId: $chatterExternalId,
    );
}

it('leaves out a conversation of another session or another agent, or one that does not exist', function (Closure $request): void {
    $conversation = Conversation::factory()->forAgent($agent = Agent::factory()->create())->create();

    expect(new ToolCallFactory()->make($request($agent, $conversation), 'lookup', '{}')->conversationExternalId)->toBeNull();
})->with([
    'another visitor\'s conversation' => [fn (Agent $agent, Conversation $conversation): StreamRequestData => tcfyRequest($agent, (string) Str::uuid7(), $conversation->external_id)],
    'another agent' => [fn (Agent $agent, Conversation $conversation): StreamRequestData => tcfyRequest(Agent::factory()->create(), $conversation->session->external_id, $conversation->external_id)],
    'an invented id' => [fn (Agent $agent, Conversation $conversation): StreamRequestData => tcfyRequest($agent, $conversation->session->external_id, (string) Str::uuid7())],
    'not an id at all' => [fn (Agent $agent, Conversation $conversation): StreamRequestData => tcfyRequest($agent, $conversation->session->external_id, 'x\' OR 1=1')],
]);
