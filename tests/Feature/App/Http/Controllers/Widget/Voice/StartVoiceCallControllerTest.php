<?php

use Domain\Agent\Models\Agent;
use Domain\Conversation\Jobs\JoinVoiceCallJob;
use Domain\Llm\Enums\AiProvider;
use Domain\Widget\WidgetHeaders;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\Support\Conversation\Voice\VoiceCalls;
use Tests\Support\Widget\EmbeddedWidget;

/**
 * A Chat Widget visitor starts a voice call in their widget conversation. The
 * widget's session must be of the agent it names, and voice is a Chat Widget
 * feature: it stays off while that surface is off, even with the Command Bar
 * on.
 */
beforeEach(function (): void {
    withLicense();

    connectAiProviders(AiProvider::OpenAi);
    config(['chatvia.ai.voice' => 'auto', 'horizon.defaults.voice.maxProcesses' => 2]);
    Queue::fake();
    VoiceCalls::fakeOpenAi();

    $this->agent = Agent::factory()->create(['is_public' => true]);
    $this->agent->setSetting('allow_voice_conversation', true, 'appearance');
    EmbeddedWidget::allowSite($this->agent);
    $this->boot = EmbeddedWidget::boot($this->agent);
    $this->headers = EmbeddedWidget::headers($this->agent, [
        WidgetHeaders::SESSION_ID => $this->boot['session_id'],
        WidgetHeaders::CONVERSATION_ID => $this->boot['conversation_id'],
    ]);
});

it('opens a call in the visitor\'s widget conversation', function (): void {
    $this->withHeaders($this->headers)
        ->postJson('/widget/voice', ['sdp' => 'v=0 offer'])
        ->assertOk()
        ->assertJsonPath('conversation_id', $this->boot['conversation_id'])
        ->assertJsonPath('sdp', 'v=0 answer');

    Queue::assertPushedOn('voice', JoinVoiceCallJob::class);
});

it('refuses a session of another agent', function (): void {
    $other = Agent::factory()->create(['is_public' => true]);
    EmbeddedWidget::allowSite($other);
    $other->setSetting('allow_voice_conversation', true, 'appearance');

    $this->withHeaders([...$this->headers, WidgetHeaders::AGENT_ID => $other->external_id])
        ->postJson('/widget/voice', ['sdp' => 'v=0 offer'])
        ->assertForbidden();

    Http::assertNothingSent();
});

it('refuses while the Chat Widget surface is off', function (): void {
    $this->agent->setSetting('widget', false, 'surfaces');

    $this->withHeaders($this->headers)
        ->postJson('/widget/voice', ['sdp' => 'v=0 offer'])
        ->assertForbidden()
        ->assertJsonPath('error', 'voice_not_available');
});
