<?php

use Domain\Agent\Models\Agent;
use Domain\Conversation\Services\Streaming\Requests\StreamRequestDataFactory;
use Illuminate\Http\Request;

/**
 * The Hosted Chat and the Widget show the sources under an answer (labels and
 * public links only) only when the agent's owner turned them on. Otherwise
 * the turn never streams them, so they never reach the browser.
 */
function sourceStreamingRequests(Agent $agent): array
{
    $factory = app(StreamRequestDataFactory::class);

    return [
        $factory->forChat(Request::create('/chat', 'POST', ['message' => 'Hi']), $agent, 'session-1', 'conversation-1'),
        $factory->forWidget(Request::create('/widget', 'POST', ['content' => 'Hi']), $agent, 'session-1', 'thread-1', isPlayground: false),
    ];
}

it('hides the sources of answers by default', function (): void {
    $agent = Agent::factory()->create();

    [$hosted, $widget] = sourceStreamingRequests($agent);

    expect($agent->showsSources())->toBeFalse()
        ->and($hosted->streamSources)->toBeFalse()
        ->and($widget->streamSources)->toBeFalse();
});

it('streams the sources of answers to the hosted chat and the widget when the agent shows them', function (): void {
    $agent = Agent::factory()->create();
    $agent->setSetting('show_sources', true, 'appearance');

    [$hosted, $widget] = sourceStreamingRequests($agent);

    expect($hosted->streamSources)->toBeTrue()
        ->and($widget->streamSources)->toBeTrue();
});

it('stops streaming the sources once the owner turns them off again', function (): void {
    $agent = Agent::factory()->create();
    $agent->setSetting('show_sources', true, 'appearance');
    $agent->setSetting('show_sources', false, 'appearance');

    [$hosted, $widget] = sourceStreamingRequests($agent);

    expect($hosted->streamSources)->toBeFalse()
        ->and($widget->streamSources)->toBeFalse();
});
