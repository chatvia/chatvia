<?php

use App\Identity\Chatter\Models\Chatter;
use Domain\Agent\Models\Agent;
use Domain\Conversation\Enums\ConversationSource;
use Domain\Conversation\Models\Conversation;
use Domain\Conversation\Models\Session;
use Domain\Widget\WidgetHeaders;
use Illuminate\Support\Str;
use Tests\Support\Widget\EmbeddedWidget;

/**
 * POST /widget/conversations ("New chat") starts a widget conversation in the
 * visitor's own session, or in a session id that is new. It never starts one
 * in a session of another agent, or of another chatter on a protected agent.
 */
beforeEach(function (): void {
    $this->agent = Agent::factory()->create(['is_public' => true]);
    EmbeddedWidget::allowSite($this->agent);
});

it('starts a widget conversation in the visitor’s own session', function (): void {
    $boot = EmbeddedWidget::boot($this->agent);

    $id = $this->withHeaders(EmbeddedWidget::headers($this->agent, [WidgetHeaders::SESSION_ID => $boot['session_id']]))
        ->postJson('/widget/conversations')
        ->assertCreated()
        ->json('id');

    $conversation = Conversation::query()->where('external_id', $id)->sole();
    expect($id)->not->toBe($boot['conversation_id'])
        ->and($conversation->source)->toBe(ConversationSource::Widget)
        ->and($conversation->session->external_id)->toBe($boot['session_id']);
});

it('starts a conversation in a session id that is new', function (): void {
    $sessionId = (string) Str::uuid7();

    $id = $this->withHeaders(EmbeddedWidget::headers($this->agent, [WidgetHeaders::SESSION_ID => $sessionId]))
        ->postJson('/widget/conversations')
        ->assertSuccessful()
        ->json('id');

    expect(Conversation::query()->where('external_id', $id)->sole()->session->external_id)->toBe($sessionId);
});

it('starts no conversation in a session of another agent', function (): void {
    $session = Session::factory()->create(['agent_id' => Agent::factory()->create()->id]);

    $this->withHeaders(EmbeddedWidget::headers($this->agent, [WidgetHeaders::SESSION_ID => $session->external_id]))
        ->postJson('/widget/conversations')
        ->assertNotFound();

    expect(Conversation::query()->where('chat_session_id', $session->id)->count())->toBe(0);
});

it('starts no conversation in another chatter’s session on a protected agent', function (): void {
    $agent = Agent::factory()->create(['is_public' => false]);
    EmbeddedWidget::allowSite($agent);
    $jane = Chatter::factory()->grantedTo($agent)->create();
    $omar = Chatter::factory()->grantedTo($agent)->create();
    $janeSession = Session::query()->where('external_id', EmbeddedWidget::bootSignedIn($agent, $jane)['session_id'])->sole();

    $this->withHeaders(EmbeddedWidget::signedInHeaders($agent, $omar, [WidgetHeaders::SESSION_ID => $janeSession->external_id]))
        ->postJson('/widget/conversations')
        ->assertNotFound();

    expect($janeSession->conversations()->count())->toBe(1);
});
