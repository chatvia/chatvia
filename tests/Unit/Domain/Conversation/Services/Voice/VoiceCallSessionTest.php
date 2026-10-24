<?php

use Domain\Agent\Models\Agent;
use Domain\Conversation\Actions\Voice\RunVoiceCallAction;
use Domain\Conversation\Enums\MessageRole;
use Domain\Conversation\Events\AssistantReplyCompleted;
use Domain\Conversation\Jobs\StoreMessageJob;
use Domain\Conversation\Models\Conversation;
use Domain\Conversation\Services\Voice\VoiceCallSession;
use Domain\Conversation\Services\Voice\VoiceCallSlots;
use Domain\Conversation\Services\Voice\VoiceCallStore;
use Domain\Llm\Contracts\RealtimeCalls;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Tests\Support\Conversation\Voice\RealtimeEvents;
use Tests\Support\Conversation\Voice\RecordingVoiceConnection;
use Tests\Support\Conversation\Voice\ScriptedRealtimeCalls;
use Tests\Support\Conversation\Voice\VoiceCalls;

/**
 * The server's side of a voice call ({@see VoiceCallSession}, run by
 * {@see RunVoiceCallAction}), fed provider events by hand: it turns listening
 * on once joined, asks for a reply when the visitor stops talking, saves both
 * sides in order (the reply counts against the message limit), and hangs up
 * on End or at the time limit, freeing the worker.
 */
beforeEach(function (): void {
    Queue::fake();
    Event::fake([AssistantReplyCompleted::class]);
    app()->instance(RealtimeCalls::class, $this->calls = new ScriptedRealtimeCalls);

    $this->agent = Agent::factory()->create(['is_public' => true]);
    // Guardrails off, so the real guards return the words unchanged without a classifier call.
    $this->agent->setSetting('enabled', false, 'guardrails');
    $this->call = VoiceCalls::started(Conversation::factory()->forAgent($this->agent)->hostedChat()->create());

    $this->session = app(VoiceCallSession::class)->for($this->call, $this->agent);
    $this->session->opened($this->connection = new RecordingVoiceConnection);
});

it('turns listening on once it has joined, leaving the reply to itself', function (): void {
    $update = $this->connection->ofType('session.update')[0];

    expect($update['session']['audio']['input']['turn_detection'])->toBe([
        'type' => 'semantic_vad',
        'create_response' => false,
        'interrupt_response' => true,
    ]);
});

it('asks for a reply when the visitor stops talking', function (): void {
    $this->session->received(RealtimeEvents::committed('item_1'));

    expect($this->connection->ofType('response.create'))->toHaveCount(1)
        ->and($this->connection->ofType('response.create')[0]['response']['metadata'])->toBe(['turn' => 'item_1']);
});

it('saves the visitor\'s words before the reply, and counts the reply', function (): void {
    $this->session->received(RealtimeEvents::committed('item_1'));
    $this->session->received(RealtimeEvents::created('resp_1'));
    // The answer is done before the words are written down: it waits.
    $this->session->received(RealtimeEvents::spoken('resp_1', 'We open at nine.', ['turn' => 'item_1']));
    expect(VoiceCalls::messages($this->call, $this->agent))->toBe([]);

    $this->session->received(RealtimeEvents::transcribed('item_1', 'When do you open?'));

    $messages = VoiceCalls::messages($this->call, $this->agent);
    expect(array_column($messages, 'role'))->toBe([MessageRole::User, MessageRole::Assistant])
        ->and(array_column($messages, 'content'))->toBe(['When do you open?', 'We open at nine.'])
        ->and($messages[1]->metadata)->toBe(['voice' => true])
        // The realtime model is not in the catalog.
        ->and($messages[1]->llm_id)->toBeNull();

    Event::assertDispatchedTimes(AssistantReplyCompleted::class, 1);
    Queue::assertPushed(StoreMessageJob::class, 2);
    Queue::assertPushed(StoreMessageJob::class, fn (StoreMessageJob $job): bool => ($job->payload->prompt_tokens ?? null) === 120);
});

it('hangs up within a second of the visitor pressing End', function (): void {
    app(VoiceCallStore::class)->end($this->call);

    $this->session->tick();

    expect($this->connection->ofType('session.close'))->toHaveCount(1)
        ->and($this->calls->hungUp)->toBe(['rtc_test']);
});

it('says goodbye when the call reaches its time limit', function (): void {
    $this->travel(11)->minutes();

    $this->session->tick();

    expect($this->connection->ofType('response.create')[0]['response']['instructions'])->toContain('time for this call is up');
});

it('saves a reply whose words were never written down, frees the worker and forgets the call', function (): void {
    $slots = app(VoiceCallSlots::class);
    $slots->take($this->call->call_id, 600);

    $this->session->received(RealtimeEvents::committed('item_1'));
    $this->session->received(RealtimeEvents::created('resp_1'));
    $this->session->received(RealtimeEvents::spoken('resp_1', 'Happy to help.', ['turn' => 'item_1']));
    $this->session->closed();

    $messages = VoiceCalls::messages($this->call, $this->agent);
    expect(array_column($messages, 'role'))->toBe([MessageRole::User, MessageRole::Assistant])
        ->and($messages[0]->content)->toBe('(Voice message that could not be transcribed)')
        ->and(app(VoiceCallStore::class)->find($this->call->call_id))->toBeNull();

    config(['horizon.defaults.voice.maxProcesses' => 1]);
    expect($slots->take('next-call', 60))->toBeTrue();
});
