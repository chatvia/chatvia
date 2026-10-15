<?php

use App\Http\Chat\HostedChatAgent;
use App\Http\Middleware\Auth\AuthenticateChatViaSignInToken;
use App\Identity\Chatter\Models\Chatter;
use App\Identity\Workspace\Services\AgentSignInTokenService;
use Domain\Agent\Models\Agent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

beforeEach(function (): void {
    $this->agent = Agent::factory()->create(['is_public' => false]);

    // A chatter granted the agent: without that grant canAccessAgent() would
    // refuse and verify() would return null.
    $this->endUser = Chatter::factory()->grantedTo($this->agent)->create();

    $this->service = app(AgentSignInTokenService::class);
    $this->middleware = app(AuthenticateChatViaSignInToken::class);
});

it('logs the end-user in on a valid token and strips it from the URL', function (): void {
    $token = $this->service->issue($this->endUser, $this->agent)['token'];
    $request = signInChatRequest($this->agent, $token);

    $response = $this->middleware->handle($request, fn (): Response => new Response('next'));

    expect($response->getStatusCode())->toBe(302)
        ->and($response->headers->get('Location'))->not->toContain('token=')
        ->and(Auth::guard('chat')->id())->toBe($this->endUser->id);
});

it('passes through anonymously when no token is present', function (): void {
    $request = signInChatRequest($this->agent, null);

    $response = $this->middleware->handle($request, fn (): Response => new Response('next'));

    expect($response->getContent())->toBe('next')
        ->and(Auth::guard('chat')->check())->toBeFalse();
});

it('ignores an unknown or expired token (no login)', function (): void {
    $request = signInChatRequest($this->agent, 'bogus-token');

    $this->middleware->handle($request, fn (): Response => new Response('next'));

    expect(Auth::guard('chat')->check())->toBeFalse();
});

it('ignores a token minted for a different agent (no login)', function (): void {
    $other = Agent::factory()->create(['workspace_id' => $this->agent->workspace_id, 'is_public' => false]);
    $token = $this->service->issue($this->endUser, $other)['token'];

    $request = signInChatRequest($this->agent, $token);

    $this->middleware->handle($request, fn (): Response => new Response('next'));

    expect(Auth::guard('chat')->check())->toBeFalse();
});

it('keeps the end-user authed across the post-sign-in redirect (full HTTP flow)', function (): void {
    $token = $this->service->issue($this->endUser, $this->agent)['token'];
    $chat = '/chat/'.$this->agent->slug;

    $this->get("{$chat}?token={$token}")
        ->assertRedirect($chat);

    $this->get($chat)->assertOk();

    expect(auth('chat')->id())->toBe($this->endUser->id);
});

describe('compact HMAC credential (?token=cvh1.…)', function (): void {
    it('logs the end-user in on a valid HMAC token and strips it from the URL', function (): void {
        $token = $this->service->issueHmac($this->endUser, $this->agent)['token'];
        $request = signInChatRequest($this->agent, $token);

        $response = $this->middleware->handle($request, fn (): Response => new Response('next'));

        expect($response->getStatusCode())->toBe(302)
            ->and($response->headers->get('Location'))->not->toContain('token=')
            ->and(Auth::guard('chat')->id())->toBe($this->endUser->id);
    });

    it('ignores an HMAC token minted for a different agent (no login)', function (): void {
        $other = Agent::factory()->create(['workspace_id' => $this->agent->workspace_id, 'is_public' => false]);
        $token = $this->service->issueHmac($this->endUser, $other)['token'];

        $request = signInChatRequest($this->agent, $token);

        $this->middleware->handle($request, fn (): Response => new Response('next'));

        expect(Auth::guard('chat')->check())->toBeFalse();
    });

    it('ignores a tampered or malformed HMAC token (no login)', function (): void {
        $request = signInChatRequest($this->agent, 'cvh1.user_123.not-a-real-credential');

        $this->middleware->handle($request, fn (): Response => new Response('next'));

        expect(Auth::guard('chat')->check())->toBeFalse();
    });

    it('keeps the end-user authed across the post-sign-in redirect (full HTTP flow) with HMAC', function (): void {
        $token = $this->service->issueHmac($this->endUser, $this->agent)['token'];
        $chat = '/chat/'.$this->agent->slug;

        $this->get("{$chat}?token={$token}")
            ->assertRedirect($chat);

        $this->get($chat)->assertOk();

        expect(auth('chat')->id())->toBe($this->endUser->id);
    });
});

function signInChatRequest(Agent $agent, ?string $token): Request
{
    $request = Request::create(
        "/chat/{$agent->slug}",
        'GET',
        $token !== null ? ['token' => $token] : [],
    );
    $request->setLaravelSession(app('session.store'));
    // What the route middleware does first on a /chat request.
    HostedChatAgent::remember($request, $agent);
    Auth::shouldUse('chat');

    return $request;
}
