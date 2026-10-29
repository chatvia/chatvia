<?php

use App\Http\Middleware\Access\ScopeRequestToWorkspace;
use App\Identity\Workspace\Models\User;
use Domain\Agent\Models\Agent;
use Domain\Conversation\Topic\Models\Topic;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * The `workspace.scope` route guard ties a dashboard or API request to the
 * caller's workspace: jobs the request queues carry its id, and a record of
 * another workspace named in the URL answers 404 before any controller runs.
 */
function srwRequest(User $user, string $parameter, Model $record): Request
{
    $request = Request::create('/test');
    $request->setUserResolver(fn (): User => $user);
    $route = new Route(['GET'], '/test/{'.$parameter.'}', ['uses' => fn (): null => null]);
    $route->bind($request);
    $route->setParameter($parameter, $record);
    $request->setRouteResolver(fn (): Route => $route);

    return $request;
}

function srwHandle(Request $request): int
{
    return app(ScopeRequestToWorkspace::class)
        ->handle($request, fn (): Response => response('ok'))
        ->getStatusCode();
}

it('lets a record of the caller\'s workspace through and shares its id with queued jobs', function (): void {
    $owner = User::factory()->create();

    $status = srwHandle(srwRequest($owner, 'agent', createAgent($owner)));

    expect($status)->toBe(200)
        ->and(Context::getHidden('workspace_id'))->toBe($owner->workspace()->id)
        ->and(Context::get('workspace_id'))->toBeNull();
});

it('answers 404 for a record of another workspace but lets a shared built-in record through', function (): void {
    $caller = User::factory()->create();
    $foreignAgent = Agent::factory()->create();
    $systemTopic = Topic::query()->create(['topic' => 'General '.Str::random(6), 'description' => 'Built in']);

    expect(fn (): int => srwHandle(srwRequest($caller, 'agent', $foreignAgent)))->toThrow(NotFoundHttpException::class)
        ->and(srwHandle(srwRequest($caller, 'topic', $systemTopic)))->toBe(200);
});

it('answers 404 for a teammate of another workspace and lets one of its own through', function (): void {
    $owner = User::factory()->create();
    $ownTeammate = User::factory()->employee($owner)->create();
    $foreignTeammate = User::factory()->employee()->create();

    expect(srwHandle(srwRequest($owner, 'member', $ownTeammate)))->toBe(200)
        ->and(fn (): int => srwHandle(srwRequest($owner, 'member', $foreignTeammate)))
        ->toThrow(NotFoundHttpException::class);
});
