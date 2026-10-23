<?php

use App\Http\Middleware\Access\EnsureAgentPermission;
use App\Http\Middleware\Access\EnsureUserCanAccessAgent;
use App\Identity\Workspace\Models\User;
use Domain\Agent\Models\Agent;
use Illuminate\Contracts\Routing\ResponseFactory;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Pest\Expectation;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Middleware access gates: small classes that decide pass/deny based on the
 * authenticated user + the route binding. They're invoked on every request
 * to a gated route, so a bug here breaks the route silently. The tests
 * build a Request, attach the bound user/agent, call handle(), and assert
 * either pass-through (next ran) or the right kind of deny (exception or
 * redirect).
 */
function mwPassThrough(): Closure
{
    return fn (Request $r): ResponseFactory|\Illuminate\Http\Response => response('ok', 200);
}

function mwRequestWithUser(User $user): Request
{
    $request = Request::create('/test');
    $request->setUserResolver(fn (): User => $user);

    return $request;
}

/** Bind an Agent into the route container so Route::route('agent') returns it. */
function mwRequestForAgent(User $user, Agent $agent): Request
{
    $request = mwRequestWithUser($user);
    $route = new Route(['GET'], '/test/{agent}', ['uses' => fn (): null => null]);
    $route->bind($request);
    $route->setParameter('agent', $agent);
    $request->setRouteResolver(fn (): Route => $route);

    return $request;
}

/*
|--------------------------------------------------------------------------
| EnsureUserCanAccessAgent
|--------------------------------------------------------------------------
*/

it('EnsureUserCanAccessAgent passes for the workspace owner accessing their own agent', function (): void {
    $agent = Agent::factory()->create();
    $owner = $agent->workspace->owner;

    // `Gate::authorize()` resolves the actor via Auth::user(), not the request,
    // so we have to actually log the user in for the policy lookup to find them.
    $this->actingAs($owner);

    $response = app(EnsureUserCanAccessAgent::class)
        ->handle(mwRequestForAgent($owner, $agent), mwPassThrough());

    expect($response->getStatusCode())->toBe(200)
        ->and($response->getContent())->toBe('ok');
});

it('EnsureUserCanAccessAgent answers 404 for a user of another workspace', function (): void {
    $agent = Agent::factory()->create();
    $intruder = Agent::factory()->create()->workspace->owner; // different workspace owner
    $this->actingAs($intruder);

    expect(fn () => app(EnsureUserCanAccessAgent::class)
        ->handle(mwRequestForAgent($intruder, $agent), mwPassThrough()))
        ->toThrow(NotFoundHttpException::class);
});

/*
|--------------------------------------------------------------------------
| EnsureAgentPermission
|--------------------------------------------------------------------------
*/

it('EnsureAgentPermission passes for the owner of the agent\'s team', function (): void {
    $owner = User::factory()->create();
    $agent = createAgent($owner);
    $this->actingAs($owner);

    $response = app(EnsureAgentPermission::class)
        ->handle(mwRequestForAgent($owner, $agent), mwPassThrough(), 'agent.setup');

    expect($response->getContent())->toBe('ok');
});

it('EnsureAgentPermission denies a user without the permission with 403, or the status the route asks for', function (string $status): void {
    $agent = Agent::factory()->create();
    $stranger = User::factory()->create();
    $this->actingAs($stranger);

    $arguments = $status === '403' ? ['agent.setup'] : ['agent.setup', $status];

    expect(fn () => app(EnsureAgentPermission::class)->handle(mwRequestForAgent($stranger, $agent), mwPassThrough(), ...$arguments))
        ->toThrow(fn (HttpException $e): Expectation => expect($e->getStatusCode())->toBe((int) $status));
})->with(['403', '404']);
