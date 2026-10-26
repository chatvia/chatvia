<?php

use Domain\Agent\Models\Agent;
use Domain\Conversation\Services\Voice\VoiceCallStore;
use Domain\Llm\Enums\AiProvider;
use Domain\Widget\WidgetHeaders;
use Illuminate\Support\Facades\Queue;
use Tests\Support\Conversation\Voice\VoiceCalls;
use Tests\Support\Widget\EmbeddedWidget;

/** A Chat Widget visitor presses End on their own voice call. */
// Voice conversations need a license key; the free-use limit itself is
// covered in VoiceAvailabilityTest.
beforeEach(fn () => withLicense());

it('hangs up the visitor\'s call', function (): void {
    connectAiProviders(AiProvider::OpenAi);
    config(['chatvia.ai.voice' => 'auto', 'horizon.defaults.voice.maxProcesses' => 1]);
    Queue::fake();
    VoiceCalls::fakeOpenAi();
    $agent = Agent::factory()->create(['is_public' => true]);
    $agent->setSetting('allow_voice_conversation', true, 'appearance');
    EmbeddedWidget::allowSite($agent);
    $boot = EmbeddedWidget::boot($agent);
    $headers = EmbeddedWidget::headers($agent, [
        WidgetHeaders::SESSION_ID => $boot['session_id'],
        WidgetHeaders::CONVERSATION_ID => $boot['conversation_id'],
    ]);
    $callId = $this->withHeaders($headers)->postJson('/widget/voice', ['sdp' => 'v=0 offer'])->json('call_id');

    $this->withHeaders($headers)->deleteJson('/widget/voice/calls/'.$callId)->assertNoContent();

    expect(app(VoiceCallStore::class)->isEnded($callId))->toBeTrue();
});
