<?php

use App\Http\Chat\HostedChatAgent;
use App\Http\Middleware\Chat\EnsureChatIsAccessible;
use App\Http\Middleware\Chat\ResolveHostedChatAgent;
use App\Http\Widget\WidgetAgent;
use Domain\Agent\Models\Agent;
use Domain\Agent\Services\AgentLocator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\URL;
use Inertia\Response as InertiaResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Rules of the hosted-chat middleware stack: who is sent to the login page, how
 * the agent is resolved, and what an unnamed agent gets.
 */
it('sends a guest of a protected agent to that agent\'s login page', function (): void {
    $agent = Agent::factory()->create(['is_public' => false]);

    $request = Request::create("/chat/{$agent->slug}/unnamed", 'GET');
    $request->setLaravelSession(app('session.store'));
    HostedChatAgent::remember($request, $agent);
    URL::defaults(['agentSlug' => $agent->slug]);

    $response = app(EnsureChatIsAccessible::class)->handle($request, fn (): Response => new Response('next'));

    expect($response->getStatusCode())->toBe(302)
        ->and($response->headers->get('Location'))->toEndWith("/chat/{$agent->slug}/login");
});

it('resolves the agent once, before the chat group, on every chat route', function (): void {
    $middleware = app('router')->gatherRouteMiddleware(Route::getRoutes()->getByName('chat.home'));

    expect(collect($middleware)->filter(fn (mixed $m): bool => $m === ResolveHostedChatAgent::class))->toHaveCount(1)
        ->and(collect($middleware)->search(ResolveHostedChatAgent::class))
        ->toBeLessThan(collect($middleware)->search(EnsureChatIsAccessible::class));
});

it('memoizes the widget agent lookup per request', function (): void {
    $agent = Agent::factory()->create();
    request()->headers->set('X-AGENT-ID', $agent->external_id);

    $first = WidgetAgent::from(request());

    DB::enableQueryLog();

    expect(WidgetAgent::from(request()))->toBe($first)
        ->and($first?->is($agent))->toBeTrue()
        ->and(DB::getQueryLog())->toBe([]);
});

it('looks the widget agent up again when the header names another agent', function (): void {
    [$first, $second] = Agent::factory()->count(2)->create();
    $request = Request::create('/widget/agents/settings');

    $request->headers->set('X-AGENT-ID', $first->external_id);
    $resolvedFirst = WidgetAgent::from($request);

    $request->headers->set('X-AGENT-ID', $second->external_id);

    expect($resolvedFirst?->is($first))->toBeTrue()
        ->and(WidgetAgent::from($request)?->is($second))->toBeTrue();
});

it('keeps the resolved hosted-chat agent on the request', function (): void {
    $agent = Agent::factory()->create();
    $request = Request::create("/chat/{$agent->slug}");

    HostedChatAgent::remember($request, $agent);

    DB::enableQueryLog();

    expect(HostedChatAgent::from($request)?->is($agent))->toBeTrue()
        ->and(DB::getQueryLog())->toBe([]);
});

it('resolves no hosted-chat agent when the request names none, even if an agent has no slug', function (): void {
    // Looking agents up by a NULL slug would match any agent whose slug is NULL.
    Agent::factory()->create(['slug' => null]);

    $request = Request::create('http://localhost/chat');
    $request->setLaravelSession(app('session.store'));

    expect(HostedChatAgent::from($request))->toBeNull()
        ->and(AgentLocator::resolveAgentBySlug(''))->toBeNull();

    // The middleware shows the not-found page instead of serving that agent.
    $response = app(EnsureChatIsAccessible::class)->handle($request, fn (): Response => new Response('next'));

    expect($response)->toBeInstanceOf(InertiaResponse::class);
});
