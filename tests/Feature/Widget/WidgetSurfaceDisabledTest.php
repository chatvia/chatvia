<?php

use Domain\Agent\Models\Agent;
use Domain\Conversation\Models\Session;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Tests\Support\Widget\EmbeddedWidget;

/**
 * The Chat Widget and the Command Bar can each be turned off per agent from
 * the Deploy page. Both embeds run on the same `/widget/*` API with identical
 * requests, so the server refuses the agent's chat calls once BOTH are off
 * (a 404, as the hosted chat gives when its own surface is off) and keeps
 * answering while either one is on, so the other embed keeps working.
 */
beforeEach(function (): void {
    $this->agent = Agent::factory()->create(['is_public' => true]);
    EmbeddedWidget::allowSite($this->agent);

    $this->headers = EmbeddedWidget::headers($this->agent);
    $this->chatHeaders = EmbeddedWidget::chatHeaders($this->agent);
});

/** Turns off the named surfaces (`widget`, `bar`) as the Deploy page does. */
function turnOffEmbedSurfaces(Agent $agent, string ...$surfaces): void
{
    foreach ($surfaces as $surface) {
        $agent->setSetting($surface, false, 'surfaces');
    }
}

it('keeps streaming while the Chat Widget or the Command Bar is on', function (array $off): void {
    turnOffEmbedSurfaces($this->agent, ...$off);

    $response = $this->withHeaders($this->chatHeaders)
        ->post('/widget/stream', ['content' => 'Hello'], ['Accept' => 'text/event-stream']);

    $response->assertOk();

    expect($response->baseResponse)->toBeInstanceOf(StreamedResponse::class);
})->with([
    'both on' => [[]],
    'only the Command Bar on' => [['widget']],
    'only the Chat Widget on' => [['bar']],
]);

it('refuses the chat calls once both embeds are off', function (string $method, string $uri, array $body): void {
    turnOffEmbedSurfaces($this->agent, 'widget', 'bar');

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

it('starts no session once both embeds are off', function (): void {
    turnOffEmbedSurfaces($this->agent, 'widget', 'bar');
    $sessionsBefore = Session::query()->count();

    $this->withHeaders($this->headers)
        ->postJson('/widget/sessions/init')
        ->assertNotFound()
        ->assertJsonPath('code', 'widget_disabled');

    expect(Session::query()->count())->toBe($sessionsBefore);
});

it('still tells the embed script that both embeds are off', function (): void {
    turnOffEmbedSurfaces($this->agent, 'widget', 'bar');

    $this->withHeaders($this->headers)
        ->getJson('/widget/agents/settings')
        ->assertOk()
        ->assertJsonPath('widget_enabled', false)
        ->assertJsonPath('bar_enabled', false);
});

it('answers again once one embed is turned back on', function (): void {
    turnOffEmbedSurfaces($this->agent, 'widget', 'bar');
    $this->agent->setSetting('bar', true, 'surfaces');

    $this->withHeaders($this->chatHeaders)
        ->post('/widget/stream', ['content' => 'Hello'], ['Accept' => 'text/event-stream'])
        ->assertOk();
});
