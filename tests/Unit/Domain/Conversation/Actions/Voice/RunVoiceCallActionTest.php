<?php

use Domain\Agent\Models\Agent;
use Domain\Conversation\Actions\Voice\RunVoiceCallAction;
use Domain\Conversation\Events\AssistantReplyCompleted;
use Domain\Conversation\Models\Conversation;
use Domain\Conversation\Services\Voice\VoiceCallStore;
use Domain\Llm\Contracts\RealtimeCallHandler;
use Domain\Llm\Contracts\RealtimeCalls;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Tests\Support\Conversation\Voice\RealtimeEvents;
use Tests\Support\Conversation\Voice\RecordingVoiceConnection;
use Tests\Support\Conversation\Voice\ScriptedRealtimeCalls;
use Tests\Support\Conversation\Voice\VoiceCalls;

/**
 * The voice worker joins a started call and runs it until it closes, then
 * forgets it. A call that ended before the worker got to it is only cleaned
 * up.
 */
beforeEach(function (): void {
    Queue::fake();
    Event::fake([AssistantReplyCompleted::class]);
    app()->instance(RealtimeCalls::class, $this->calls = new ScriptedRealtimeCalls);

    $this->agent = Agent::factory()->create(['is_public' => true]);
    // Guardrails off, so the real guards return the words unchanged without a classifier call.
    $this->agent->setSetting('enabled', false, 'guardrails');
    $this->call = VoiceCalls::started(Conversation::factory()->forAgent($this->agent)->hostedChat()->create());
});

it('joins the call and saves what was said', function (): void {
    $this->calls->script = function (RealtimeCallHandler $handler): void {
        $handler->opened(new RecordingVoiceConnection);
        $handler->received(RealtimeEvents::committed('item_1'));
        $handler->received(RealtimeEvents::transcribed('item_1', 'Hello?'));
        $handler->received(RealtimeEvents::created('resp_1'));
        $handler->received(RealtimeEvents::spoken('resp_1', 'Hi! How can I help?', ['turn' => 'item_1']));
        $handler->closed();
    };

    app(RunVoiceCallAction::class)->execute($this->call);

    expect($this->calls->joined)->toBe(['rtc_test'])
        ->and(array_column(VoiceCalls::messages($this->call, $this->agent), 'content'))->toBe(['Hello?', 'Hi! How can I help?'])
        ->and(app(VoiceCallStore::class)->find($this->call->call_id))->toBeNull();
});

it('only cleans up a call that ended before the worker got to it', function (): void {
    app(VoiceCallStore::class)->end($this->call);

    app(RunVoiceCallAction::class)->execute($this->call);

    expect($this->calls->joined)->toBe([])
        ->and(app(VoiceCallStore::class)->find($this->call->call_id))->toBeNull();
});
