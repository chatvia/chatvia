<?php

use Domain\Agent\Models\Agent;
use Domain\Conversation\Models\Session;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Tests\Support\Widget\EmbeddedWidget;

/**
 * The Chat Widget and the Command Bar run on the same `/widget/*` API, so the
 * server refuses the agent's chat calls (a 404 `widget_disabled`, as the
 * hosted chat gives when its own surface is off) once BOTH are switched off
 * on the Deploy page, or once the agent's workspace is deactivated
 * (EnsureWidgetIsAccessible). While either embed is on, the other keeps
 * working. The boot settings tell each embed whether to mount.
 */
beforeEach(function (): void {
    $this->agent = Agent::factory()->create(['is_public' => true]);
    EmbeddedWidget::allowSite($this->agent);

    $this->headers = EmbeddedWidget::headers($this->agent);
    $this->chatHeaders = EmbeddedWidget::chatHeaders($this->agent);
});

/** Turns off the named surfaces (`widget`, `bar`) as the Deploy page does. */
function wtoTurnOff(Agent $agent, string ...$surfaces): void
{
    foreach ($surfaces as $surface) {
        $agent->setSetting($surface, false, 'surfaces');
    }
}

/** What turns the agent's widget off, and what turns it back on. */
dataset('wtoCauses', [
    'both embeds are switched off' => [[
        'off' => fn (Agent $agent) => wtoTurnOff($agent, 'widget', 'bar'),
        'on' => fn (Agent $agent) => $agent->setSetting('bar', true, 'surfaces'),
    ]],
    'the workspace is deactivated' => [[
        'off' => fn (Agent $agent) => deactivateWorkspaceOf($agent),
        'on' => fn (Agent $agent) => reactivateWorkspaceOf($agent),
    ]],
]);

it('keeps streaming while the Chat Widget or the Command Bar is on', function (array $off): void {
    wtoTurnOff($this->agent, ...$off);

    $response = $this->withHeaders($this->chatHeaders)
        ->post('/widget/stream', ['content' => 'Hello'], ['Accept' => 'text/event-stream']);

    $response->assertOk();
    expect($response->baseResponse)->toBeInstanceOf(StreamedResponse::class);
})->with([
    'both on' => [[]],
    'only the Command Bar on' => [['widget']],
    'only the Chat Widget on' => [['bar']],
]);

it('refuses every chat call once both embeds are off', function (string $method, string $uri, array $body): void {
    wtoTurnOff($this->agent, 'widget', 'bar');

    $this->withHeaders($this->chatHeaders)
        ->json($method, $uri, $body)
        ->assertNotFound()
        ->assertExactJson(['error' => 'This chat is turned off.', 'code' => 'widget_disabled']);
})->with([
    'stream' => ['POST', '/widget/stream', ['content' => 'Hello']],
    'attachment upload' => ['POST', '/widget/conversations/attachments', []],
    'conversation title' => ['POST', '/widget/conversations/title', []],
    'voice call' => ['POST', '/widget/voice', ['sdp' => 'v=0 offer']],
    'new conversation' => ['POST', '/widget/conversations', []],
    'message history' => ['GET', '/widget/conversations/messages', []],
]);

it('starts no session while the widget is turned off', function (array $cause): void {
    $cause['off']($this->agent);
    $sessionsBefore = Session::query()->count();

    $this->withHeaders($this->headers)
        ->postJson('/widget/sessions/init')
        ->assertNotFound()
        ->assertJsonPath('code', 'widget_disabled');

    expect(Session::query()->count())->toBe($sessionsBefore);
})->with('wtoCauses');

it('tells both embeds not to mount while the widget is turned off', function (array $cause): void {
    $cause['off']($this->agent);

    $this->withHeaders($this->headers)
        ->getJson('/widget/agents/settings')
        ->assertOk()
        ->assertJsonPath('widget_enabled', false)
        ->assertJsonPath('bar_enabled', false);
})->with('wtoCauses');

it('serves the widget again once it is turned back on', function (array $cause): void {
    $cause['off']($this->agent);
    $cause['on']($this->agent);

    $this->withHeaders($this->headers)->postJson('/widget/sessions/init')->assertSuccessful();
    $this->withHeaders($this->headers)->getJson('/widget/agents/settings')->assertJsonPath('bar_enabled', true);
})->with('wtoCauses');
