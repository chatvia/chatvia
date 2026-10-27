<?php

use Domain\Agent\Models\Agent;
use Domain\Conversation\Data\Turn\StreamRequestData;
use Domain\Conversation\Data\Turn\StreamRequestMessageData;
use Domain\Conversation\Models\Conversation;
use Domain\Conversation\Services\ChatTurnStreamer;
use Illuminate\Support\Facades\Bus;
use Spectra\Spectra;
use Tests\Support\Llm\ScriptedLlmProvider;

/**
 * A web chat turn (hosted chat, widget, API) tags the AI calls Spectra
 * records with the conversation it serves, so the Trackables screen
 * aggregates calls and cost per conversation. When the tagger tags nothing
 * is SpectraTurnTaggerTest's.
 */
beforeEach(function (): void {
    Bus::fake();
});

/** Run one real web turn in an existing conversation and return it. */
function cstagTurn(): Conversation
{
    bindFakeLlmProvider(ScriptedLlmProvider::answering('We open at nine.'));

    $agent = Agent::factory()->create(['is_public' => true, 'llm_id' => testLlm()->id]);
    $agent->setSetting('enabled', false, 'guardrails');
    $conversation = Conversation::factory()->forAgent($agent)->create();

    iterator_to_array(app(ChatTurnStreamer::class)->stream(new StreamRequestData(
        agent: $agent->fresh(),
        message: new StreamRequestMessageData(model: $agent->llm, message: 'When do you open?'),
        externalSessionId: $conversation->session->external_id,
        conversationId: $conversation->external_id,
        saveUserMessageImmediately: true,
    )), preserve_keys: false);

    return $conversation;
}

it('tags the turn with its conversation while Spectra records', function (): void {
    config(['spectra.enabled' => true]);

    $conversation = cstagTurn();

    $request = app(Spectra::class)->startRequest('openai', 'gpt-test');
    expect($request->trackableType)->toBe('conversation')
        ->and($request->trackableId)->toBe($conversation->id);
});
