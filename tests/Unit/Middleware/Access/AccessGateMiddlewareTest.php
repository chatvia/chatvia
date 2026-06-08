<?php

use App\Http\Middleware\Access\EnsureUserCanAccessAgent;
use App\Http\Middleware\Access\EnsureUserHasCapability;
use App\Http\Middleware\Access\EnsureUserHasSubscription;
use App\Identity\Workspace\Models\User;
use Domain\Agent\Models\Agent;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Routing\ResponseFactory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Symfony\Component\HttpKernel\Exception\HttpException;

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
    $owner = $agent->user;

    // `Gate::authorize()` resolves the actor via Auth::user(), not the request,
    // so we have to actually log the user in for the policy lookup to find them.
    $this->actingAs($owner);

    $response = app(EnsureUserCanAccessAgent::class)
        ->handle(mwRequestForAgent($owner, $agent), mwPassThrough());

    expect($response->getStatusCode())->toBe(200)
        ->and($response->getContent())->toBe('ok');
});

it('EnsureUserCanAccessAgent throws AuthorizationException for an unrelated workspace user', function (): void {
    $agent = Agent::factory()->create();
    $intruder = Agent::factory()->create()->user; // different workspace owner
    $this->actingAs($intruder);

    expect(fn () => app(EnsureUserCanAccessAgent::class)
        ->handle(mwRequestForAgent($intruder, $agent), mwPassThrough()))
        ->toThrow(AuthorizationException::class);
});

/*
|--------------------------------------------------------------------------
| EnsureUserHasSubscription
|--------------------------------------------------------------------------
*/

it('EnsureUserHasSubscription redirects an unsubscribed workspace owner to billing.packages', function (): void {
    $user = User::factory()->create();
    // The UserObserver auto-creates user_billing but no subscription row, so
    // ->subscribed() is false. This is the "fresh signup" state.

    $response = app(EnsureUserHasSubscription::class)
        ->handle(mwRequestWithUser($user), mwPassThrough());

    expect($response)->toBeInstanceOf(RedirectResponse::class)
        ->and($response->getTargetUrl())->toContain('/billing');
});

it('EnsureUserHasSubscription passes for an admin user regardless of subscription state', function (): void {
    $admin = User::factory()->admin()->create();

    $response = app(EnsureUserHasSubscription::class)
        ->handle(mwRequestWithUser($admin), mwPassThrough());

    expect($response->getStatusCode())->toBe(200);
});

it('EnsureUserHasSubscription passes for an unauthenticated guest (auth handled elsewhere)', function (): void {
    $request = Request::create('/test');
    $request->setUserResolver(fn (): null => null);

    $response = app(EnsureUserHasSubscription::class)->handle($request, mwPassThrough());

    expect($response->getStatusCode())->toBe(200);
});

/*
|--------------------------------------------------------------------------
| EnsureUserHasCapability
|--------------------------------------------------------------------------
*/

it('EnsureUserHasCapability passes admins without checking the plan', function (): void {
    $admin = User::factory()->admin()->create();

    $response = app(EnsureUserHasCapability::class)
        ->handle(mwRequestWithUser($admin), mwPassThrough(), 'handoff');

    expect($response->getStatusCode())->toBe(200);
});

it('EnsureUserHasCapability throws ValueError for an unknown capability name (typo guard)', function (): void {
    $user = User::factory()->create();

    expect(fn () => app(EnsureUserHasCapability::class)
        ->handle(mwRequestWithUser($user), mwPassThrough(), 'not-a-real-capability'))
        ->toThrow(ValueError::class);
});

it('EnsureUserHasCapability aborts(403) when the workspace plan does not include the capability', function (): void {
    // Starter plan (default for fresh user) does NOT include `handoff` —
    // see config/billing.php packages.starter.features.
    $user = User::factory()->create();

    expect(fn () => app(EnsureUserHasCapability::class)
        ->handle(mwRequestWithUser($user), mwPassThrough(), 'handoff'))
        ->toThrow(HttpException::class);
});
