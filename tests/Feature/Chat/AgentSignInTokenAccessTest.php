<?php

use App\Auth\Models\User;
use App\Auth\Services\AgentSignInTokenService;
use App\Http\Middleware\AuthenticateChatViaSignInToken;
use App\Teams\Services\TeamService;
use Domain\Agents\Models\Agent;
use Domain\Auth\Actions\CreateChatterAction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

beforeEach(function (): void {
    $this->owner = User::factory()->create();
    app(TeamService::class)->createDefaultTeam($this->owner);
    $this->agent = makeSignInAgent($this->owner);

    // The chatter signs in to the agent — CreateChatterAction provisions the
    // user and attaches the agent_user grant in one shot. Without that grant
    // canAccessAgent() would refuse and verify() would return null.
    $this->endUser = app(CreateChatterAction::class)->execute($this->owner, $this->agent, 'Jane');

    $this->service = app(AgentSignInTokenService::class);
    $this->middleware = app(AuthenticateChatViaSignInToken::class);
});

it('logs the end-user in on a valid token and strips it from the URL', function (): void {
    $token = $this->service->issue($this->endUser, $this->agent)['token'];
    $request = signInChatRequest($this->agent, $token);

    $response = $this->middleware->handle($request, fn (): Response => new Response('next'));

    expect($response->getStatusCode())->toBe(302)
        ->and($response->headers->get('Location'))->not->toContain('token=')
        ->and(Auth::guard('web')->id())->toBe($this->endUser->id);
});

it('passes through anonymously when no token is present', function (): void {
    $request = signInChatRequest($this->agent, null);

    $response = $this->middleware->handle($request, fn (): Response => new Response('next'));

    expect($response->getContent())->toBe('next')
        ->and(Auth::guard('web')->check())->toBeFalse();
});

it('ignores an unknown or expired token (no login)', function (): void {
    $request = signInChatRequest($this->agent, 'bogus-token');

    $this->middleware->handle($request, fn (): Response => new Response('next'));

    expect(Auth::guard('web')->check())->toBeFalse();
});

it('ignores a token minted for a different agent (no login)', function (): void {
    $other = makeSignInAgent($this->owner);
    $token = $this->service->issue($this->endUser, $other)['token'];

    $request = signInChatRequest($this->agent, $token);

    $this->middleware->handle($request, fn (): Response => new Response('next'));

    expect(Auth::guard('web')->check())->toBeFalse();
});

it('keeps the end-user authed across the post-sign-in redirect (full HTTP flow)', function (): void {
    $token = $this->service->issue($this->endUser, $this->agent)['token'];
    $host = $this->agent->sub_domain.'.chatvia.test';

    $this->get("https://{$host}/chat?token={$token}")
        ->assertRedirect("https://{$host}/chat");

    $second = $this->get("https://{$host}/chat");
    if ($second->isRedirect()) {
        expect($second->headers->get('Location'))->not->toContain('/chat/login');
    }

    expect(auth('web')->id())->toBe($this->endUser->id);
});

function makeSignInAgent(User $owner): Agent
{
    return Agent::create([
        'user_id' => $owner->id,
        'team_id' => $owner->primaryTeam()->id,
        'name' => 'Private Agent',
        'type' => 'knowledge_base',
        'external_id' => Str::random(14),
        'sub_domain' => Str::lower(Str::random(12)),
        'is_public' => false,
    ]);
}

function signInChatRequest(Agent $agent, ?string $token): Request
{
    $request = Request::create(
        "https://{$agent->sub_domain}.chatvia.test/chat",
        'GET',
        $token !== null ? ['token' => $token] : [],
    );
    $request->setLaravelSession(app('session.store'));

    return $request;
}
