<?php

use Domain\Agent\Models\Agent;
use Domain\Conversation\Services\Streaming\Requests\StreamRequestDataFactory;
use Illuminate\Http\Request;

/**
 * The Hosted Chat and the Widget both show the sources under an answer, so
 * both turns stream them (labels and public links only).
 */
it('streams the sources of answers to the hosted chat and the widget', function (): void {
    $agent = Agent::factory()->create();
    $factory = app(StreamRequestDataFactory::class);

    $hosted = $factory->forChat(Request::create('/chat', 'POST', ['message' => 'Hi']), $agent, 'session-1', 'conversation-1');
    $widget = $factory->forWidget(Request::create('/widget', 'POST', ['content' => 'Hi']), $agent, 'session-1', 'thread-1', isPlayground: false);

    expect($hosted->streamSources)->toBeTrue()
        ->and($widget->streamSources)->toBeTrue();
});
