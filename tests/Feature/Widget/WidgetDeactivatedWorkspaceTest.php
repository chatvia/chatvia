<?php

use Domain\Agent\Models\Agent;
use Domain\Conversation\Models\Session;
use Tests\Support\Widget\EmbeddedWidget;

/**
 * A deactivated workspace's agents stop answering in the Chat Widget and the
 * Command Bar, exactly as when both embeds are switched off: the boot
 * settings tell both embeds not to mount, and every chat call gets a 404
 * `widget_disabled`. Reactivating the workspace brings them back.
 */
beforeEach(function (): void {
    $this->agent = Agent::factory()->create(['is_public' => true]);
    EmbeddedWidget::allowSite($this->agent);

    $this->headers = EmbeddedWidget::headers($this->agent);
    $this->chatHeaders = EmbeddedWidget::chatHeaders($this->agent);
});

it('tells both embeds not to mount once the workspace is deactivated', function (): void {
    $this->withHeaders($this->headers)->getJson('/widget/agents/settings')
        ->assertOk()
        ->assertJsonPath('widget_enabled', true)
        ->assertJsonPath('bar_enabled', true);

    deactivateWorkspaceOf($this->agent);

    $this->withHeaders($this->headers)->getJson('/widget/agents/settings')
        ->assertOk()
        ->assertJsonPath('widget_enabled', false)
        ->assertJsonPath('bar_enabled', false);
});

it('refuses the chat calls once the workspace is deactivated', function (string $method, string $uri, array $body): void {
    deactivateWorkspaceOf($this->agent);

    $this->withHeaders($this->chatHeaders)
        ->json($method, $uri, $body)
        ->assertNotFound()
        ->assertExactJson(['error' => 'This chat is turned off.', 'code' => 'widget_disabled']);
})->with([
    'stream' => ['POST', '/widget/stream', ['content' => 'Hello']],
    'voice call' => ['POST', '/widget/voice', ['sdp' => 'v=0 offer']],
    'new conversation' => ['POST', '/widget/conversations', []],
    'message history' => ['GET', '/widget/conversations/messages', []],
]);

it('starts no session once the workspace is deactivated', function (): void {
    deactivateWorkspaceOf($this->agent);
    $sessionsBefore = Session::query()->count();

    $this->withHeaders($this->headers)
        ->postJson('/widget/sessions/init')
        ->assertNotFound()
        ->assertJsonPath('code', 'widget_disabled');

    expect(Session::query()->count())->toBe($sessionsBefore);
});

it('serves the widget again once the workspace is reactivated', function (): void {
    deactivateWorkspaceOf($this->agent);
    reactivateWorkspaceOf($this->agent);

    $this->withHeaders($this->headers)->postJson('/widget/sessions/init')->assertSuccessful();
    $this->withHeaders($this->headers)->getJson('/widget/agents/settings')->assertJsonPath('widget_enabled', true);
});
