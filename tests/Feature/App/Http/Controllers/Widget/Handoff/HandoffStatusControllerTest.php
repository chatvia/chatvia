<?php

use Domain\Agent\Models\Agent;
use Domain\Widget\WidgetHeaders;
use Illuminate\Testing\TestResponse;
use Tests\Support\Widget\EmbeddedWidget;

/**
 * GET /widget/handoff/status tells the widget who answers the visitor's
 * conversation. A conversation the visitor cannot see, or an id that is not a
 * UUID, reads as answered by the AI, so the call leaks nothing.
 */
beforeEach(function (): void {
    $this->agent = Agent::factory()->create(['is_public' => true]);
    EmbeddedWidget::allowSite($this->agent);
    $this->boot = EmbeddedWidget::boot($this->agent);
});

function hscStatus(string $conversationId): TestResponse
{
    return test()->withHeaders(EmbeddedWidget::headers(test()->agent, [WidgetHeaders::SESSION_ID => test()->boot['session_id']]))
        ->getJson('/widget/handoff/status?conversation_id='.$conversationId);
}

it('reports the handoff status of the visitor’s conversation', function (): void {
    hscStatus($this->boot['conversation_id'])
        ->assertOk()
        ->assertJson(['conversation_id' => $this->boot['conversation_id'], 'handoff_status' => 'ai', 'is_human_controlled' => false]);
});

it('reports the AI as answering a conversation the visitor cannot see', function (): void {
    hscStatus('01a11e0a-0000-7000-8000-000000000000')
        ->assertOk()
        ->assertExactJson([
            'conversation_id' => '01a11e0a-0000-7000-8000-000000000000',
            'handoff_status' => 'ai',
            'is_human_controlled' => false,
            'admin_name' => null,
        ]);
});

it('treats a conversation id that is not a UUID as unknown', function (): void {
    hscStatus('nope')
        ->assertOk()
        ->assertJson(['handoff_status' => 'ai']);
});
