<?php

use App\Http\Middleware\Access\EnsureUserCanAccessAgent;
use App\Identity\Workspace\Models\User;
use Domain\Agent\Models\Agent;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Route;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * The `can.access.agent` route guard lets the agent's own workspace through
 * and answers 404 to anyone else, so another workspace never learns the
 * agent exists. The policy reads the signed-in user, so each test signs one
 * in as well as handing it to the request.
 */
function euaRequest(User $user, Agent $agent): Request
{
    $request = Request::create('/test');
    $request->setUserResolver(fn (): User => $user);
    $route = new Route(['GET'], '/test/{agent}', ['uses' => fn (): null => null]);
    $route->bind($request);
    $route->setParameter('agent', $agent);
    $request->setRouteResolver(fn (): Route => $route);

    return $request;
}

function euaHandle(Request $request): Response
{
    return app(EnsureUserCanAccessAgent::class)->handle($request, fn (): Response => new Response('ok'));
}

it('lets the agent\'s own workspace through and answers 404 to another workspace', function (): void {
    $agent = Agent::factory()->create();
    $owner = $agent->workspace->owner;
    $intruder = Agent::factory()->create()->workspace->owner;

    auth()->login($owner);
    $ownResponse = euaHandle(euaRequest($owner, $agent));
    auth()->login($intruder);

    expect($ownResponse->getContent())->toBe('ok')
        ->and(fn (): Response => euaHandle(euaRequest($intruder, $agent)))->toThrow(NotFoundHttpException::class);
});
