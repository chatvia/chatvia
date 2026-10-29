<?php

use App\Identity\Chatter\Models\Chatter;
use App\Identity\Workspace\Models\User;
use Domain\Agent\Models\Agent;
use Domain\Conversation\Data\Turn\StreamRequestData;
use Domain\Conversation\Services\Streaming\Requests\StreamRequestDataFactory;
use Illuminate\Http\Request;

/**
 * Building a turn from a Hosted Chat or Widget request: the sources under an
 * answer stream only when the agent's owner turned them on, a chatter is
 * stamped as the turn's chatter and staff only as its audit actor, and the
 * client's request id is scoped to the agent, session and conversation.
 */
function srdfRequests(Agent $agent): array
{
    $factory = app(StreamRequestDataFactory::class);

    return [
        $factory->forChat(Request::create('/chat', 'POST', ['message' => 'Hi']), $agent, 'session-1', 'conversation-1'),
        $factory->forWidget(Request::create('/widget', 'POST', ['content' => 'Hi']), $agent, 'session-1', 'thread-1', isPlayground: false),
    ];
}

it('streams the sources of answers only when the agent shows them', function (): void {
    $hidden = Agent::factory()->create();
    $shown = Agent::factory()->create();
    $shown->setSetting('sources_display', 'list', 'appearance');

    [$hiddenHosted, $hiddenWidget] = srdfRequests($hidden);
    [$shownHosted, $shownWidget] = srdfRequests($shown);

    expect([$hiddenHosted->streamSources, $hiddenWidget->streamSources])->toBe([false, false])
        ->and([$shownHosted->streamSources, $shownWidget->streamSources])->toBe([true, true]);
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

it('stamps a chatter as the chatter of the turn, and staff only as its audit actor', function (Closure $turn): void {
    $agent = Agent::factory()->create();
    $chatter = Chatter::factory()->grantedTo($agent)->create();
    $staff = $agent->workspace->owner;
    $factory = app(StreamRequestDataFactory::class);

    $chatterTurn = $turn($factory, $agent, $chatter);
    $staffTurn = $turn($factory, $agent, $staff);

    expect([$chatterTurn->chatterId, $chatterTurn->chatterName, $chatterTurn->chatterExternalId, $chatterTurn->actorUserId, $chatterTurn->actorName])
        ->toBe([$chatter->id, $chatter->name, $chatter->external_id, null, $chatter->name])
        ->and([$staffTurn->chatterId, $staffTurn->chatterName, $staffTurn->chatterExternalId, $staffTurn->actorUserId, $staffTurn->actorName])
        ->toBe([null, null, null, $staff->id, $staff->name]);
})->with('srdf surfaces');

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
