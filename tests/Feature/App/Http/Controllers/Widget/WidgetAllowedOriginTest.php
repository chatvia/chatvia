<?php

use Domain\Agent\Models\Agent;
use Domain\Widget\WidgetHeaders;
use Tests\Support\Widget\EmbeddedWidget;

/**
 * Every widget API route, except the version probe and agents/verify, must be
 * called from one of the agent's allowed sites (VerifyWidgetOrigin, applied to
 * the widget route group), and must name the agent. WidgetVerificationTest
 * owns the matching of sites; EmbeddedWidget::boot() is the allowed call.
 */
beforeEach(function (): void {
    $this->agent = Agent::factory()->create(['is_public' => true]);
    EmbeddedWidget::allowSite($this->agent);
});

it('rejects a widget call from a site that is not allowed', function (string $method, string $uri): void {
    $this->withHeaders(EmbeddedWidget::headers($this->agent, [WidgetHeaders::SESSION_ID => 'session-id', 'Origin' => 'https://evil.example']))
        ->json($method, $uri)
        ->assertForbidden()
        ->assertExactJson(['error' => 'Domain not allowed']);
})->with([
    'settings' => ['GET', '/widget/agents/settings'],
    'auth exchange' => ['POST', '/widget/auth/exchange'],
    'session init' => ['POST', '/widget/sessions/init'],
    'conversations' => ['GET', '/widget/conversations'],
    'create conversation' => ['POST', '/widget/conversations'],
    'delete conversations' => ['DELETE', '/widget/conversations'],
    'messages' => ['GET', '/widget/conversations/messages'],
    'stream' => ['POST', '/widget/stream'],
    'feedback' => ['POST', '/widget/feedback'],
    'broadcast auth' => ['POST', '/widget/broadcasting/auth'],
    'typing' => ['POST', '/widget/typing'],
    'handoff request' => ['POST', '/widget/handoff/request'],
    'handoff status' => ['GET', '/widget/handoff/status'],
    'handoff end' => ['POST', '/widget/handoff/end'],
    'heartbeat' => ['POST', '/widget/heartbeat'],
    'mark read' => ['POST', '/widget/mark-read'],
]);

it('rejects a widget call that names no agent', function (): void {
    $this->withHeaders([WidgetHeaders::SESSION_ID => 'session-id', 'Origin' => 'https://'.EmbeddedWidget::SITE])
        ->postJson('/widget/heartbeat')
        ->assertForbidden();
});
