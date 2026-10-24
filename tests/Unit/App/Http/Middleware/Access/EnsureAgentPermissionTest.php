<?php

use App\Http\Middleware\Access\EnsureAgentPermission;
use App\Identity\Workspace\Models\User;
use Domain\Agent\Models\Agent;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Route;
use Pest\Expectation;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * The `agent.permission:…` route guard lets through a member whose team role
 * holds the permission on the agent named in the URL, and refuses anyone
 * else with 403, or with the status the route asks for. The policy reads the
 * signed-in user, so each test signs one in as well as handing it to the
 * request.
 */
function eapRequest(User $user, Agent $agent): Request
{
    $request = Request::create('/test');
    $request->setUserResolver(fn (): User => $user);
    $route = new Route(['GET'], '/test/{agent}', ['uses' => fn (): null => null]);
    $route->bind($request);
    $route->setParameter('agent', $agent);
    $request->setRouteResolver(fn (): Route => $route);

    return $request;
}

it('lets the owner of the agent\'s team through', function (): void {
    $owner = User::factory()->create();
    $agent = createAgent($owner);
    auth()->login($owner);

    $response = app(EnsureAgentPermission::class)->handle(eapRequest($owner, $agent), fn (): Response => new Response('ok'), 'agent.setup');

    expect($response->getContent())->toBe('ok');
});

it('refuses a user without the permission with 403, or the status the route asks for', function (array $arguments, int $status): void {
    $agent = Agent::factory()->create();
    $stranger = User::factory()->create();
    auth()->login($stranger);

    expect(fn () => app(EnsureAgentPermission::class)->handle(eapRequest($stranger, $agent), fn (): Response => new Response('ok'), ...$arguments))
        ->toThrow(fn (HttpException $e): Expectation => expect($e->getStatusCode())->toBe($status));
})->with([
    'by default' => [['agent.setup'], 403],
    'a route that hides the agent' => [['agent.setup', '404'], 404],
]);
