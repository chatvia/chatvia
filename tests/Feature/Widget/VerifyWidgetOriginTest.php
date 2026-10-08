<?php

use Domain\Agent\Models\Agent;

/**
 * Every widget API route — except the version probe and agents/verify — must be
 * called from one of the agent's allowed sites (VerifyWidgetOrigin, applied to
 * the widget route group). The check no longer depends on the X-AGENT-ID header
 * being present.
 */
beforeEach(function (): void {
    config(['chatvia.widget.always_allowed_origins' => []]);

    $this->agent = Agent::factory()->create(['is_public' => true]);
    $this->agent->setSetting('sites', ['customer.example'], 'widget');
});

it('rejects a widget call from a site that is not allowed', function (string $method, string $uri): void {
    $this->withHeaders([
        'X-AGENT-ID' => $this->agent->external_id,
        'X-SESSION-ID' => 'session-id',
        'Origin' => 'https://evil.example',
    ])
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
    $this->withHeaders(['X-SESSION-ID' => 'session-id', 'Origin' => 'https://customer.example'])
        ->postJson('/widget/heartbeat')
        ->assertForbidden();
});

it('lets a call from an allowed site through to the controller', function (): void {
    // Passing the origin gate reaches the controller, which then reports the
    // missing conversation id.
    $this->withHeaders([
        'X-AGENT-ID' => $this->agent->external_id,
        'X-SESSION-ID' => 'session-id',
        'Origin' => 'https://www.customer.example',
    ])
        ->getJson('/widget/handoff/status')
        ->assertStatus(400)
        ->assertJson(['error' => 'Missing required parameters']);
});

it('keeps the version probe reachable without an agent or origin', function (): void {
    $this->getJson('/widget/version')->assertOk()->assertJsonStructure(['version']);
});

it('answers agents/verify with its own verified flag', function (): void {
    $this->withHeaders(['X-AGENT-ID' => $this->agent->external_id, 'Origin' => 'https://evil.example'])
        ->getJson('/widget/agents/verify')
        ->assertForbidden()
        ->assertExactJson(['verified' => false]);

    $this->withHeaders(['X-AGENT-ID' => $this->agent->external_id, 'Origin' => 'https://customer.example'])
        ->getJson('/widget/agents/verify')
        ->assertOk()
        ->assertJson(['verified' => true]);
});

it('answers agents/verify without an agent header instead of failing', function (): void {
    $this->withHeaders(['Origin' => 'https://customer.example'])
        ->getJson('/widget/agents/verify')
        ->assertForbidden()
        ->assertExactJson(['verified' => false]);
});

it('starts a widget session from an allowed site', function (): void {
    $this->withHeaders(['X-AGENT-ID' => $this->agent->external_id, 'Origin' => 'https://customer.example'])
        ->postJson('/widget/sessions/init', ['page_url' => 'https://customer.example/pricing'])
        ->assertSuccessful();
});
