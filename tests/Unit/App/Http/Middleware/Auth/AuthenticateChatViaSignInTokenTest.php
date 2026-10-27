<?php

use App\Http\Chat\HostedChatAgent;
use App\Http\Middleware\Auth\AuthenticateChatViaSignInToken;
use App\Identity\Chatter\Models\Chatter;
use App\Identity\Workspace\Services\AgentSignInTokens;
use Domain\Agent\Models\Agent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\URL;
use Symfony\Component\HttpFoundation\Response;

/**
 * A hosted-chat address with `?token=` signs the chatter in on the `chat`
 * guard and redirects to the same address without the token. A token that
 * cannot be verified is stripped the same way and signs nobody in; no token
 * passes the request on. A browser signed in as another chatter is sent to
 * confirm the switch instead. The token rules themselves are the sign-in
 * token service's.
 */
beforeEach(function (): void {
    $this->agent = Agent::factory()->create(['is_public' => false]);

    // A chatter granted the agent: without that grant canAccessAgent() would
    // refuse and verify() would return null.
    $this->chatter = Chatter::factory()->grantedTo($this->agent)->create();
});

/** A hosted-chat request for the agent, as the route middleware hands it on. */
function acvstRequest(Agent $agent, ?string $token): Request
{
    $request = Request::create(
        "/chat/{$agent->slug}",
        'GET',
        $token !== null ? ['token' => $token] : [],
    );
    $request->setLaravelSession(app('session.store'));
    // What the route middleware does first on a /chat request.
    HostedChatAgent::remember($request, $agent);
    URL::defaults(['agentSlug' => $agent->slug]);
    Auth::shouldUse('chat');

    return $request;
}

function acvstHandle(Request $request): Response
{
    return app(AuthenticateChatViaSignInToken::class)->handle($request, fn (): Response => new Response('next'));
}

it('signs the chatter in on a valid token and strips it from the address', function (string $issue): void {
    $token = app(AgentSignInTokens::class)->{$issue}($this->chatter, $this->agent)['token'];

    $response = acvstHandle(acvstRequest($this->agent, $token));

    expect($response->getStatusCode())->toBe(302)
        ->and($response->headers->get('Location'))->not->toContain('token=')
        ->and(Auth::guard('chat')->id())->toBe($this->chatter->id);
})->with([
    'plain token' => 'issue',
    'HMAC token' => 'issueHmac',
]);

it('passes the request on anonymously when no token is present', function (): void {
    $response = acvstHandle(acvstRequest($this->agent, null));

    expect($response->getContent())->toBe('next')
        ->and(Auth::guard('chat')->check())->toBeFalse();
});

it('signs nobody in for a token it cannot verify, and still strips it from the address', function (Closure $token): void {
    $response = acvstHandle(acvstRequest($this->agent, $token($this->agent, $this->chatter)));

    expect($response->getStatusCode())->toBe(302)
        ->and($response->headers->get('Location'))->not->toContain('token=')
        ->and(Auth::guard('chat')->check())->toBeFalse();
})->with([
    'a made-up token' => [fn (): string => 'bogus-token'],
    'a tampered HMAC token' => [fn (): string => 'cvh1.user_123.not-a-real-credential'],
    'a token minted for another agent' => [fn (Agent $agent, Chatter $chatter): string => app(AgentSignInTokens::class)
        ->issue($chatter, Agent::factory()->create(['workspace_id' => $agent->workspace_id, 'is_public' => false]))['token']],
]);

it('does not switch away from another signed-in chatter, but sends the browser to confirm', function (): void {
    $signedIn = Chatter::factory()->grantedTo($this->agent)->create();
    Auth::guard('chat')->login($signedIn);
    $token = app(AgentSignInTokens::class)->issue($this->chatter, $this->agent)['token'];

    $response = acvstHandle(acvstRequest($this->agent, $token));

    expect($response->getStatusCode())->toBe(302)
        ->and($response->headers->get('Location'))->toEndWith('/switch-account')
        ->and(Auth::guard('chat')->id())->toBe($signedIn->id);
});
