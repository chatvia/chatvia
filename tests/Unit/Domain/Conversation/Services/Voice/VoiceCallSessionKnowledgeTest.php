<?php

use Domain\Agent\Models\Agent;
use Domain\Conversation\Data\Turn\KnowledgeLookupData;
use Domain\Conversation\Data\Turn\QaMatchData;
use Domain\Conversation\Events\AssistantReplyCompleted;
use Domain\Conversation\Models\Conversation;
use Domain\Conversation\Services\Turn\KnowledgeEnricher;
use Domain\Conversation\Services\Voice\VoiceCallSession;
use Domain\Conversation\Services\Voice\VoiceToolRunner;
use Domain\Llm\Contracts\RealtimeCalls;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Tests\Support\Conversation\Voice\RealtimeEvents;
use Tests\Support\Conversation\Voice\RecordingVoiceConnection;
use Tests\Support\Conversation\Voice\ScriptedRealtimeCalls;
use Tests\Support\Conversation\Voice\VoiceCalls;

/**
 * Knowledge in a voice call: an agent with knowledge starts every reply with
 * a search, says "let me check" while it searches and then asks for the rest
 * of the reply; small talk goes straight on, a visitor who talks over the
 * filler drops the waiting reply, and a tool the call did not offer is
 * refused.
 */
beforeEach(function (): void {
    Queue::fake();
    Event::fake([AssistantReplyCompleted::class]);
    app()->instance(RealtimeCalls::class, new ScriptedRealtimeCalls);

    $this->agent = Agent::factory()->create(['is_public' => true]);
    // Guardrails off, so the real guards return the words unchanged without a classifier call.
    $this->agent->setSetting('enabled', false, 'guardrails');
    $this->call = VoiceCalls::started(Conversation::factory()->forAgent($this->agent)->hostedChat()->create());

    $this->session = app(VoiceCallSession::class)->for($this->call, $this->agent);
    $this->session->opened($this->connection = new RecordingVoiceConnection);
});

it('starts every reply with a knowledge search when the agent has knowledge', function (): void {
    VoiceCalls::giveAgentKnowledge($this->agent);
    $session = app(VoiceCallSession::class)->for($this->call, $this->agent);
    $session->opened($connection = new RecordingVoiceConnection);

    $session->received(RealtimeEvents::committed('item_1'));

    expect($connection->ofType('response.create')[0]['response']['tool_choice'])->toBe(['type' => 'function', 'name' => 'search_knowledge']);
});

it('lets the model choose when the agent has no knowledge', function (): void {
    $this->session->received(RealtimeEvents::committed('item_1'));

    expect($this->connection->ofType('response.create')[0]['response'])->not->toHaveKey('tool_choice');
});

it('says "let me check" while it searches, then asks for the rest of the reply', function (): void {
    $this->mock(KnowledgeEnricher::class, fn ($mock) => $mock->shouldReceive('lookup')->andReturn(new KnowledgeLookupData(
        qaMatch: new QaMatchData(id: 7, questions: 'Opening hours?', answer: 'Nine to five, Monday to Friday.', confidenceScore: 0.99),
        sources: collect(),
    )));
    $session = app(VoiceCallSession::class)->for($this->call, $this->agent);
    $session->opened($connection = new RecordingVoiceConnection);

    $session->received(RealtimeEvents::committed('item_1'));
    $session->received(RealtimeEvents::created('resp_1'));
    $session->received(RealtimeEvents::toolCall('resp_1', 'search_knowledge', '{"query":"When are you open?"}'));

    $creates = $connection->ofType('response.create');
    expect($creates)->toHaveCount(2)
        ->and($creates[1]['response']['conversation'])->toBe('none')
        ->and($creates[1]['response']['metadata'])->toBe(['notice' => '1', 'filler' => '1']);

    $output = $connection->ofType('conversation.item.create')[0]['item'];
    expect($output['type'])->toBe('function_call_output')
        ->and($output['call_id'])->toBe('fc_1')
        ->and($output['output'])->toContain('Nine to five, Monday to Friday.')
        // The call view reads how the call ended off the id; the provider allows 32 characters.
        ->and($output['id'])->toStartWith('tool_done_')
        ->and(strlen($output['id']))->toBeLessThanOrEqual(32);

    // The answer waits until "let me check" has been said.
    $session->received(RealtimeEvents::fillerCreated());
    $session->received(RealtimeEvents::fillerDone());

    expect($connection->ofType('response.create'))->toHaveCount(3)
        ->and($connection->ofType('response.create')[2]['response']['metadata'])->toBe(['turn' => 'item_1']);
});

it('drops the waiting reply when the visitor talks over "let me check"', function (): void {
    $this->session->received(RealtimeEvents::committed('item_1'));
    $this->session->received(RealtimeEvents::created('resp_1'));
    $this->session->received(RealtimeEvents::toolCall('resp_1', 'search_knowledge', '{"query":"Opening hours"}'));
    $this->session->received(RealtimeEvents::fillerCreated());
    $this->session->received(RealtimeEvents::fillerDone('cancelled'));

    // The search's "let me check" only; no answer to the dropped question.
    expect($this->connection->ofType('response.create'))->toHaveCount(2);
});

it('goes straight on, without "let me check", for small talk', function (): void {
    $this->session->received(RealtimeEvents::committed('item_1'));
    $this->session->received(RealtimeEvents::created('resp_1'));
    $this->session->received(RealtimeEvents::toolCall('resp_1', 'search_knowledge', '{"query":""}'));

    expect($this->connection->ofType('conversation.item.create')[0]['item']['output'])->toBe(VoiceToolRunner::SMALL_TALK_RESULT)
        ->and($this->connection->ofType('response.create'))->toHaveCount(2)
        ->and($this->connection->ofType('response.create')[1]['response']['metadata'])->toBe(['turn' => 'item_1']);
});

it('refuses a tool the call did not offer', function (): void {
    $this->session->received(RealtimeEvents::committed('item_1'));
    $this->session->received(RealtimeEvents::created('resp_1'));
    $this->session->received(RealtimeEvents::toolCall('resp_1', 'delete_everything', '{}'));

    $output = $this->connection->ofType('conversation.item.create')[0]['item'];

    expect($output['output'])->toContain('is not one of the tools')
        ->and($output['id'])->toStartWith('tool_failed_');
});

it('goes on after "let me check" even when it never finishes', function (): void {
    $this->session->received(RealtimeEvents::committed('item_1'));
    $this->session->received(RealtimeEvents::created('resp_1'));
    $this->session->received(RealtimeEvents::toolCall('resp_1', 'search_knowledge', '{"query":"Opening hours"}'));

    expect($this->connection->ofType('response.create'))->toHaveCount(2);

    $this->travel(9)->seconds();
    $this->session->tick();

    expect($this->connection->ofType('response.create'))->toHaveCount(3);
});
