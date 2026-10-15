<?php

use App\Identity\Chatter\Models\Chatter;
use Domain\Agent\Models\Agent;
use Domain\Conversation\Models\Message;
use Domain\Conversation\Models\Session;
use Domain\Widget\WidgetHeaders;
use Tests\Support\Widget\EmbeddedWidget;

/**
 * On a protected widget the visitor session belongs to the signed-in chatter.
 * The stream checks that owner too, so a chatter who replays someone else's
 * session id cannot send messages in that person's conversations.
 */
beforeEach(function (): void {
    $this->agent = Agent::factory()->create(['is_public' => false]);
    EmbeddedWidget::allowSite($this->agent);

    $this->jane = Chatter::factory()->create(['workspace_id' => $this->agent->workspace_id]);
    $this->omar = Chatter::factory()->create(['workspace_id' => $this->agent->workspace_id]);
});

it('streams on the chatter\'s own session', function (): void {
    $boot = EmbeddedWidget::bootSignedIn($this->agent, $this->jane);

    expect(Session::query()->where('external_id', $boot['session_id'])->value('chatter_id'))->toBe($this->jane->id);

    $this->withHeaders(EmbeddedWidget::signedInHeaders($this->agent, $this->jane, [
        WidgetHeaders::SESSION_ID => $boot['session_id'],
        WidgetHeaders::CONVERSATION_ID => $boot['conversation_id'],
    ]))
        ->post('/widget/stream', ['content' => 'Hello'], ['Accept' => 'text/event-stream'])
        ->assertOk();
});

it('refuses to stream on the session of another chatter', function (): void {
    $boot = EmbeddedWidget::bootSignedIn($this->agent, $this->jane);
    $messagesBefore = Message::query()->count();

    $this->withHeaders(EmbeddedWidget::signedInHeaders($this->agent, $this->omar, [
        WidgetHeaders::SESSION_ID => $boot['session_id'],
        WidgetHeaders::CONVERSATION_ID => $boot['conversation_id'],
    ]))
        ->postJson('/widget/stream', ['content' => 'Hello'])
        ->assertForbidden();

    expect(Message::query()->count())->toBe($messagesBefore);
});
