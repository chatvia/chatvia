<?php

use Domain\Agent\Models\Agent;
use Domain\Conversation\Models\Conversation;
use Domain\Widget\WidgetHeaders;
use Illuminate\Support\Carbon;
use Tests\Support\Widget\EmbeddedWidget;

/**
 * POST /widget/heartbeat keeps the visitor's open conversation marked active.
 * RecordWidgetHeartbeatActionTest and TouchConversationActivityActionTest own
 * the rules (hidden conversations, the once-a-minute stamp).
 */
beforeEach(function (): void {
    $this->agent = Agent::factory()->create(['is_public' => true]);
    EmbeddedWidget::allowSite($this->agent);
    $this->boot = EmbeddedWidget::boot($this->agent);
});

it('moves the activity time of the visitor’s visible conversation forward', function (): void {
    $this->travelTo(Carbon::parse('2026-10-01 10:00:00'));
    Conversation::query()->where('external_id', $this->boot['conversation_id'])->toBase()
        ->update(['is_visible' => true, 'last_activity_at' => now()->subHour()]);

    $this->withHeaders(EmbeddedWidget::headers($this->agent, [WidgetHeaders::SESSION_ID => $this->boot['session_id']]))
        ->postJson('/widget/heartbeat', ['conversation_id' => $this->boot['conversation_id']])
        ->assertSuccessful();

    expect(Conversation::query()->where('external_id', $this->boot['conversation_id'])->sole()->last_activity_at->toDateTimeString())
        ->toBe('2026-10-01 10:00:00');
});
