<?php

use App\Identity\Chatter\Models\Chatter;
use App\Identity\Workspace\Models\User;
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

it('stamps a chatter as the chatter of the turn, and staff only as its audit actor', function (): void {
    $agent = Agent::factory()->create();
    $chatter = Chatter::factory()->grantedTo($agent)->create();
    $staff = $agent->workspace->owner;
    $factory = app(StreamRequestDataFactory::class);

    $chatRequest = fn (Chatter|User $account): Request => tap(
        Request::create('/chat', 'POST', ['message' => 'Hi']),
        fn (Request $request): Request => $request->setUserResolver(fn (): Chatter|User => $account),
    );
    $widgetRequest = Request::create('/widget', 'POST', ['content' => 'Hi']);

    $turns = [
        'hosted chat, chatter' => [$factory->forChat($chatRequest($chatter), $agent, 's', 'c'), $chatter],
        'widget, chatter' => [$factory->forWidget($widgetRequest, $agent, 's', 't', isPlayground: false, account: $chatter), $chatter],
    ];
    $staffTurns = [
        'hosted chat, staff' => $factory->forChat($chatRequest($staff), $agent, 's', 'c'),
        'widget, staff' => $factory->forWidget($widgetRequest, $agent, 's', 't', isPlayground: false, account: $staff),
    ];

    foreach ($turns as $label => [$data, $account]) {
        expect([$data->chatterId, $data->chatterName, $data->chatterExternalId, $data->actorUserId, $data->actorName])
            ->toBe([$account->id, $account->name, $account->external_id, null, $account->name], $label);
    }

    foreach ($staffTurns as $label => $data) {
        expect([$data->chatterId, $data->chatterName, $data->chatterExternalId, $data->actorUserId, $data->actorName])
            ->toBe([null, null, null, $staff->id, $staff->name], $label);
    }
});

it('marks a widget turn from the dashboard playground', function (): void {
    $agent = Agent::factory()->create();
    $factory = app(StreamRequestDataFactory::class);
    $request = Request::create('/widget', 'POST', ['content' => 'Hi']);

    expect($factory->forWidget($request, $agent, 's', 't', isPlayground: true)->source)->toBe('playground')
        ->and($factory->forWidget($request, $agent, 's', 't', isPlayground: false)->source)->toBeNull();
});
