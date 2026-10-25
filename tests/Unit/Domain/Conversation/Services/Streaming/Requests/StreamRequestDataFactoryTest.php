<?php

use App\Identity\Chatter\Models\Chatter;
use App\Identity\Workspace\Models\User;
use Domain\Agent\Models\Agent;
use Domain\Conversation\Data\Turn\StreamRequestData;
use Domain\Conversation\Services\Streaming\Requests\StreamRequestDataFactory;
use Illuminate\Http\Request;

/**
 * The Hosted Chat and the Widget show the sources under an answer (labels and
 * public links only) only when the agent's owner turned them on. Otherwise
 * the turn never streams them, so they never reach the browser.
 */
function srdfRequests(Agent $agent): array
{
    $factory = app(StreamRequestDataFactory::class);

    return [
        $factory->forChat(Request::create('/chat', 'POST', ['message' => 'Hi']), $agent, 'session-1', 'conversation-1'),
        $factory->forWidget(Request::create('/widget', 'POST', ['content' => 'Hi']), $agent, 'session-1', 'thread-1', isPlayground: false),
    ];
}

it('hides the sources of answers by default', function (): void {
    $agent = Agent::factory()->create();

    [$hosted, $widget] = srdfRequests($agent);

    expect($agent->showsSources())->toBeFalse()
        ->and($hosted->streamSources)->toBeFalse()
        ->and($widget->streamSources)->toBeFalse();
});

it('streams the sources of answers to the hosted chat and the widget when the agent shows them', function (): void {
    $agent = Agent::factory()->create();
    $agent->setSetting('sources_display', 'list', 'appearance');

    [$hosted, $widget] = srdfRequests($agent);

    expect($hosted->streamSources)->toBeTrue()
        ->and($widget->streamSources)->toBeTrue();
});

dataset('srdf surfaces', [
    'hosted chat' => [fn (StreamRequestDataFactory $factory, Agent $agent, Chatter|User $account): StreamRequestData => $factory->forChat(
        tap(Request::create('/chat', 'POST', ['message' => 'Hi']), fn (Request $request): Request => $request->setUserResolver(fn (): Chatter|User => $account)),
        $agent,
        's',
        'c',
    )],
    'widget' => [fn (StreamRequestDataFactory $factory, Agent $agent, Chatter|User $account): StreamRequestData => $factory->forWidget(
        Request::create('/widget', 'POST', ['content' => 'Hi']),
        $agent,
        's',
        't',
        isPlayground: false,
        account: $account,
    )],
]);

it('stamps a chatter as the chatter of the turn', function (Closure $turn): void {
    $agent = Agent::factory()->create();
    $chatter = Chatter::factory()->grantedTo($agent)->create();

    $data = $turn(app(StreamRequestDataFactory::class), $agent, $chatter);

    expect([$data->chatterId, $data->chatterName, $data->chatterExternalId, $data->actorUserId, $data->actorName])
        ->toBe([$chatter->id, $chatter->name, $chatter->external_id, null, $chatter->name]);
})->with('srdf surfaces');

it('stamps staff only as the audit actor of the turn', function (Closure $turn): void {
    $agent = Agent::factory()->create();
    $staff = $agent->workspace->owner;

    $data = $turn(app(StreamRequestDataFactory::class), $agent, $staff);

    expect([$data->chatterId, $data->chatterName, $data->chatterExternalId, $data->actorUserId, $data->actorName])
        ->toBe([null, null, null, $staff->id, $staff->name]);
})->with('srdf surfaces');

it('marks a widget turn from the dashboard playground', function (): void {
    $agent = Agent::factory()->create();
    $factory = app(StreamRequestDataFactory::class);
    $request = Request::create('/widget', 'POST', ['content' => 'Hi']);

    expect($factory->forWidget($request, $agent, 's', 't', isPlayground: true)->source)->toBe('playground')
        ->and($factory->forWidget($request, $agent, 's', 't', isPlayground: false)->source)->toBeNull();
});

it('scopes the client request id to the agent, session and conversation it was sent for', function (): void {
    $agent = Agent::factory()->create();
    $other = Agent::factory()->create();
    $factory = app(StreamRequestDataFactory::class);
    $id = '0199c0de-0000-7000-8000-000000000001';

    $chat = fn (Agent $agent, string $session, string $conversation): ?string => $factory->forChat(
        Request::create('/chat', 'POST', ['message' => 'Hi', 'request_id' => $id]), $agent, $session, $conversation,
    )->requestId;
    $widget = fn (Agent $agent, string $session, string $thread): ?string => $factory->forWidget(
        Request::create('/widget', 'POST', ['content' => 'Hi'], server: ['HTTP_X_REQUEST_ID' => $id]), $agent, $session, $thread, isPlayground: false,
    )->requestId;

    $ids = [
        $chat($agent, 's1', 'c1'),
        $chat($other, 's1', 'c1'),
        $chat($agent, 's2', 'c1'),
        $chat($agent, 's1', 'c2'),
        $widget($agent, 's1', 'c1'),
        $widget($other, 's1', 'c1'),
        $widget($agent, 's2', 'c1'),
    ];

    expect($ids)->each->toEndWith($id)
        ->and(collect($ids)->unique())->toHaveCount(count($ids))
        ->and($chat($agent, 's1', 'c1'))->toBe($ids[0]);
});

it('leaves a turn without a client request id unscoped', function (): void {
    $agent = Agent::factory()->create();

    [$hosted, $widget] = srdfRequests($agent);

    expect($hosted->requestId)->toBeNull()
        ->and($widget->requestId)->toBeNull();
});
