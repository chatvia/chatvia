<?php

use App\Workspace\Models\User;
use App\Http\Middleware\Chat\RedirectToAgent;
use App\Http\Middleware\Chat\ResolveAgentFromSubdomain;
use App\Team\Services\TeamService;
use Domain\Agent\Models\Agent;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Response as InertiaResponse;
use Inertia\Testing\AssertableInertia;
use Symfony\Component\HttpFoundation\Response;

function makeQueryParamAgent(bool $public = true): Agent
{
    $owner = User::factory()->create();
    app(TeamService::class)->createDefaultTeam($owner);

    return Agent::create([
        'user_id' => $owner->id,
        'team_id' => $owner->primaryTeam()->id,
        'name' => 'Query Param Agent',
        'type' => 'knowledge_base',
        'external_id' => Str::random(14),
        'sub_domain' => Str::lower(Str::random(12)),
        'system_instructions' => 'You are a helpful assistant.',
        'is_public' => $public,
    ]);
}

it('resolves an agent from the ?agent= query param on the apex domain', function (): void {
    $agent = makeQueryParamAgent();

    $request = Request::create('https://chatvia.test/chat', 'GET', ['agent' => $agent->external_id]);
    $request->setLaravelSession(app('session.store'));

    $called = false;
    $response = app(ResolveAgentFromSubdomain::class)->handle($request, function () use (&$called) {
        $called = true;

        return new Response('next');
    });

    expect($called)->toBeTrue()
        ->and($response->getContent())->toBe('next')
        ->and($request->session()->get('chat_agent'))->toBe($agent->external_id)
        ->and($request->input('agent'))->toBe($agent->external_id);
});

it('keeps the agent sticky via the session on a later param-less request', function (): void {
    $agent = makeQueryParamAgent();
    $session = app('session.store');

    $first = Request::create('https://chatvia.test/chat', 'GET', ['agent' => $agent->external_id]);
    $first->setLaravelSession($session);
    app(ResolveAgentFromSubdomain::class)->handle($first, fn (): Response => new Response('next'));

    expect($session->get('chat_agent'))->toBe($agent->external_id);

    $second = Request::create('https://chatvia.test/chat', 'GET');
    $second->setLaravelSession($session);

    $called = false;
    $response = app(ResolveAgentFromSubdomain::class)->handle($second, function () use (&$called) {
        $called = true;

        return new Response('next');
    });

    expect($called)->toBeTrue()
        ->and($response->getContent())->toBe('next')
        ->and($second->agentSlug())->toBe($agent->external_id);
});

it('renders AgentNotFound for an unknown ?agent= value', function (): void {
    makeQueryParamAgent();

    $request = Request::create('https://chatvia.test/chat', 'GET', ['agent' => 'does-not-exist']);
    $request->setLaravelSession(app('session.store'));

    $called = false;
    $response = app(ResolveAgentFromSubdomain::class)->handle($request, function () use (&$called) {
        $called = true;

        return new Response('next');
    });

    expect($called)->toBeFalse()
        ->and($response)->toBeInstanceOf(InertiaResponse::class);
});

it('lets an explicit ?agent= override the host subdomain', function (): void {
    $agent = makeQueryParamAgent();

    $request = Request::create('https://someoneelse.chatvia.test/chat', 'GET', ['agent' => $agent->external_id]);

    expect($request->agentSlug())->toBe($agent->external_id);
});

it('falls back to the host subdomain when no ?agent= is present', function (): void {
    $request = Request::create('https://acme.chatvia.test/chat', 'GET');

    expect($request->agentSlug())->toBe('acme');
});

it('redirects the apex root with ?agent= into /chat carrying the param', function (): void {
    $agent = makeQueryParamAgent();

    $request = Request::create('https://chatvia.test/', 'GET', ['agent' => $agent->external_id]);

    $response = app(RedirectToAgent::class)->handle($request, fn (): Response => new Response('next'));

    expect($response->getStatusCode())->toBe(302)
        ->and($response->headers->get('Location'))->toContain('/chat')
        ->and($response->headers->get('Location'))->toContain('agent='.$agent->external_id);
});

it('does not redirect when already on a /chat path', function (): void {
    $agent = makeQueryParamAgent();

    $request = Request::create('https://chatvia.test/chat', 'GET', ['agent' => $agent->external_id]);

    $called = false;
    $response = app(RedirectToAgent::class)->handle($request, function () use (&$called) {
        $called = true;

        return new Response('next');
    });

    expect($called)->toBeTrue()
        ->and($response->getContent())->toBe('next');
});

it('routes an apex ?agent= request through the chat middleware group (full HTTP)', function (): void {
    makeQueryParamAgent();

    // Unknown id short-circuits in ResolveAgentFromSubdomain (the first chat
    // middleware), so this proves the apex /chat route + group wiring end-to-end
    // without depending on the downstream billing/capacity fixtures.
    $this->get('https://chatvia.test/chat?agent=does-not-exist')
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page->component('agents/chat/AgentNotFound'));
});
