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
 * {@see RunVoiceCallAction}), fed provider events by hand: it saves both
 * sides in order (the reply counts against the message limit), and hangs up
 * on End, saving a reply whose words were never written down and freeing the
 * worker.
 */
beforeEach(function (): void {
    Queue::fake();
    Event::fake([AssistantReplyCompleted::class]);
    app()->instance(RealtimeCalls::class, $this->calls = new ScriptedRealtimeCalls);

    $this->agent = Agent::factory()->create(['is_public' => true]);
    $this->agent->setSetting('enabled', false, 'guardrails');
    $this->call = VoiceCalls::started(Conversation::factory()->forAgent($this->agent)->hostedChat()->create());

    $this->session = app(VoiceCallSession::class)->for($this->call, $this->agent);
    $this->session->opened($this->connection = new RecordingVoiceConnection);
});

it('saves the visitor\'s words before the reply, and counts the reply', function (): void {
    $this->session->received(RealtimeEvents::committed('item_1'));
    $this->session->received(RealtimeEvents::created('resp_1'));
    $this->session->received(RealtimeEvents::spoken('resp_1', 'We open at nine.', ['turn' => 'item_1']));
    expect(VoiceCalls::messages($this->call, $this->agent))->toBe([]);

    $this->session->received(RealtimeEvents::transcribed('item_1', 'When do you open?'));

    $messages = VoiceCalls::messages($this->call, $this->agent);
    expect(array_column($messages, 'role'))->toBe([MessageRole::User, MessageRole::Assistant])
        ->and(array_column($messages, 'content'))->toBe(['When do you open?', 'We open at nine.'])
        ->and($messages[1]->metadata)->toBe(['voice' => true])
        ->and($messages[1]->llm_id)->toBeNull();

    Event::assertDispatchedTimes(AssistantReplyCompleted::class, 1);
    Queue::assertPushed(StoreMessageJob::class, 2);
    Queue::assertPushed(StoreMessageJob::class, fn (StoreMessageJob $job): bool => ($job->payload->prompt_tokens ?? null) === 120);
});

it('hangs up when the visitor presses End, saves a reply whose words were never written down and frees the worker', function (): void {
    $slots = app(VoiceCallSlots::class);
    $slots->take($this->call->call_id, 600);
    $this->session->received(RealtimeEvents::committed('item_1'));
    $this->session->received(RealtimeEvents::created('resp_1'));
    $this->session->received(RealtimeEvents::spoken('resp_1', 'Happy to help.', ['turn' => 'item_1']));
    app(VoiceCallStore::class)->end($this->call);

    $this->session->tick();
    $this->session->closed();

    $messages = VoiceCalls::messages($this->call, $this->agent);
    config(['horizon.defaults.voice.maxProcesses' => 1]);
    expect($this->connection->ofType('session.close'))->toHaveCount(1)
        ->and($this->calls->hungUp)->toBe(['rtc_test'])
        ->and(array_column($messages, 'role'))->toBe([MessageRole::User, MessageRole::Assistant])
        ->and($messages[0]->content)->toBe('(Voice message that could not be transcribed)')
        ->and(app(VoiceCallStore::class)->find($this->call->call_id))->toBeNull()
        ->and($slots->take('next-call', 60))->toBeTrue();
});
