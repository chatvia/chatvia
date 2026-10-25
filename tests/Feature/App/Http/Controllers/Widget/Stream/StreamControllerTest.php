<?php

use App\Http\Responses\SseResponder;
use App\Identity\Chatter\Models\Chatter;
use Domain\Agent\Models\Agent;
use Domain\Conversation\Models\Message;
use Domain\Conversation\Models\Session;
use Domain\Widget\WidgetHeaders;
use Illuminate\Support\Str;
use Tests\Support\Http\BufferedSseResponder;
use Tests\Support\Llm\ScriptedLlmProvider;
use Tests\Support\Widget\EmbeddedWidget;

/**
 * POST /widget/stream runs a chat turn in the visitor's conversation. It
 * needs the conversation id, refuses a message over the admin's length limit
 * (as JSON, even when the widget does not ask for it), and refuses a session
 * that is not the caller's:
 * one of another agent, or, on a protected agent, one of another signed-in
 * chatter. It answers with the model the chatter picked when the agent
 * offers it. The turn itself is covered by the ChatStreamService tests.
 */
beforeEach(function (): void {
    $this->agent = Agent::factory()->create(['is_public' => true]);
    EmbeddedWidget::allowSite($this->agent);
});

it('refuses to stream without a conversation id', function (): void {
    $boot = EmbeddedWidget::boot($this->agent);

    $this->withHeaders(EmbeddedWidget::headers($this->agent, [WidgetHeaders::SESSION_ID => $boot['session_id']]))
        ->postJson('/widget/stream', ['content' => 'Hello'])
        ->assertStatus(400);
});

it('refuses a message longer than the admin\'s limit with a JSON 422', function (): void {
    config(['chatvia.guardrails.max_message_chars' => 300]);
    $boot = EmbeddedWidget::boot($this->agent);

    $this->withHeaders(EmbeddedWidget::headers($this->agent, [
        WidgetHeaders::SESSION_ID => $boot['session_id'],
        WidgetHeaders::CONVERSATION_ID => $boot['conversation_id'],
    ]))
        ->post('/widget/stream', ['content' => Str::repeat('a', 301)])
        ->assertStatus(422)
        ->assertJsonValidationErrors('content');
});

it('refuses to stream on a session of another agent', function (): void {
    $other = Agent::factory()->create(['is_public' => true]);
    EmbeddedWidget::allowSite($other);
    $foreign = EmbeddedWidget::boot($other);

    $this->withHeaders(EmbeddedWidget::headers($this->agent, [
        WidgetHeaders::SESSION_ID => $foreign['session_id'],
        WidgetHeaders::CONVERSATION_ID => $foreign['conversation_id'],
    ]))
        ->postJson('/widget/stream', ['content' => 'Hello'])
        ->assertForbidden();
});

it('streams on a signed-in chatter’s own session of a protected agent', function (): void {
    $agent = Agent::factory()->create(['is_public' => false]);
    EmbeddedWidget::allowSite($agent);
    $jane = Chatter::factory()->create(['workspace_id' => $agent->workspace_id]);
    $boot = EmbeddedWidget::bootSignedIn($agent, $jane);

    expect(Session::query()->where('external_id', $boot['session_id'])->value('chatter_id'))->toBe($jane->id);

    $this->withHeaders(EmbeddedWidget::signedInHeaders($agent, $jane, [
        WidgetHeaders::SESSION_ID => $boot['session_id'],
        WidgetHeaders::CONVERSATION_ID => $boot['conversation_id'],
    ]))
        ->post('/widget/stream', ['content' => 'Hello'], ['Accept' => 'text/event-stream'])
        ->assertOk();
});

it('refuses to stream on the session of another chatter of a protected agent', function (): void {
    $agent = Agent::factory()->create(['is_public' => false]);
    EmbeddedWidget::allowSite($agent);
    $jane = Chatter::factory()->create(['workspace_id' => $agent->workspace_id]);
    $omar = Chatter::factory()->create(['workspace_id' => $agent->workspace_id]);
    $boot = EmbeddedWidget::bootSignedIn($agent, $jane);
    $messagesBefore = Message::query()->count();

    $this->withHeaders(EmbeddedWidget::signedInHeaders($agent, $omar, [
        WidgetHeaders::SESSION_ID => $boot['session_id'],
        WidgetHeaders::CONVERSATION_ID => $boot['conversation_id'],
    ]))
        ->postJson('/widget/stream', ['content' => 'Hello'])
        ->assertForbidden();

    expect(Message::query()->count())->toBe($messagesBefore);
});

it('answers with the model the chatter picked when the agent offers it', function (): void {
    $own = testLlm('own-model');
    $offered = testLlm('offered-model');
    $this->agent->forceFill(['llm_id' => $own->id])->saveQuietly();
    config(['chatvia.ai.chatter_model_choice' => true]);
    $this->agent->setSetting('enabled', true, 'model_choice');
    $this->agent->setSetting('models', [$offered->id], 'model_choice');
    $llm = ScriptedLlmProvider::answering('The answer.');
    bindFakeLlmProvider($llm);
    app()->instance(SseResponder::class, new BufferedSseResponder);
    $boot = EmbeddedWidget::boot($this->agent);

    $this->withHeaders(EmbeddedWidget::headers($this->agent, [
        WidgetHeaders::SESSION_ID => $boot['session_id'],
        WidgetHeaders::CONVERSATION_ID => $boot['conversation_id'],
    ]))
        ->post('/widget/stream', ['content' => 'Hello', 'model_id' => $offered->id], ['Accept' => 'text/event-stream'])
        ->assertOk()
        ->streamedContent();

    expect($llm->sentModels)->toBe(['offered-model']);
});
