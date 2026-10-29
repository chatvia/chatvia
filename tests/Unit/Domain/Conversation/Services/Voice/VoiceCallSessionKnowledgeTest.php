<?php

use Domain\Agent\Models\Agent;
use Domain\Conversation\Events\AssistantReplyCompleted;
use Domain\Conversation\Models\Conversation;
use Domain\Conversation\Services\Voice\VoiceCallSession;
use Domain\Llm\Contracts\RealtimeCalls;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Tests\Support\Conversation\Voice\RealtimeEvents;
use Tests\Support\Conversation\Voice\RecordingVoiceConnection;
use Tests\Support\Conversation\Voice\ScriptedRealtimeCalls;
use Tests\Support\Conversation\Voice\VoiceCalls;

/**
 * Knowledge in a voice call: an agent with knowledge starts every reply with
 * a search, one without lets the model choose, and a tool the call did not
 * offer is refused.
 */
beforeEach(function (): void {
    Queue::fake();
    Event::fake([AssistantReplyCompleted::class]);
    app()->instance(RealtimeCalls::class, new ScriptedRealtimeCalls);

    $this->agent = Agent::factory()->create(['is_public' => true]);
    $this->agent->setSetting('enabled', false, 'guardrails');
    $this->call = VoiceCalls::started(Conversation::factory()->forAgent($this->agent)->hostedChat()->create());

    $this->session = app(VoiceCallSession::class)->for($this->call, $this->agent);
    $this->session->opened($this->connection = new RecordingVoiceConnection);
});

it('starts every reply with a knowledge search when the agent has knowledge, and lets the model choose when it has none', function (): void {
    $this->session->received(RealtimeEvents::committed('item_1'));
    VoiceCalls::giveAgentKnowledge($this->agent);
    $session = app(VoiceCallSession::class)->for($this->call, $this->agent);
    $session->opened($connection = new RecordingVoiceConnection);

    $session->received(RealtimeEvents::committed('item_1'));

    expect($this->connection->ofType('response.create')[0]['response'])->not->toHaveKey('tool_choice')
        ->and($connection->ofType('response.create')[0]['response']['tool_choice'])->toBe(['type' => 'function', 'name' => 'search_knowledge']);
});

it('refuses a tool the call did not offer', function (): void {
    $this->session->received(RealtimeEvents::committed('item_1'));
    $this->session->received(RealtimeEvents::created('resp_1'));
    $this->session->received(RealtimeEvents::toolCall('resp_1', 'delete_everything', '{}'));

    $output = $this->connection->ofType('conversation.item.create')[0]['item'];

    expect($output['output'])->toContain('is not one of the tools')
        ->and($output['id'])->toStartWith('tool_failed_');
});
