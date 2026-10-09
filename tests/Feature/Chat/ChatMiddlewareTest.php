<?php

use App\Http\Chat\HostedChatAgent;
use App\Http\Middleware\Chat\EnsureChatIsAccessible;
use App\Http\Middleware\Chat\RedirectToAgent;
use App\Http\Widget\WidgetAgent;
use Domain\Agent\Models\Agent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Inertia\Response as InertiaResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Small regressions in the hosted-chat middleware stack.
 */
it('sends a guest to the login page when the request has no route name', function (): void {
    $agent = Agent::factory()->create(['is_public' => false]);

    $request = Request::create("https://{$agent->sub_domain}.chatvia.test/chat/unnamed", 'GET');
    $request->setLaravelSession(app('session.store'));

    // A null route name used to reach str_starts_with(), which is deprecated;
    // turn deprecations into failures for this call.
    set_error_handler(function (int $level, string $message): never {
        throw new ErrorException($message, 0, $level);
    }, E_DEPRECATED);

    try {
        $response = app(EnsureChatIsAccessible::class)->handle($request, fn (): Response => new Response('next'));
    } finally {
        restore_error_handler();
    }

    expect($response->getStatusCode())->toBe(302)
        ->and($response->headers->get('Location'))->toContain('/chat/login');
});

it('runs RedirectToAgent once on chat routes', function (): void {
    $middleware = app('router')->gatherRouteMiddleware(Route::getRoutes()->getByName('chat.home'));

    expect(collect($middleware)->filter(fn (mixed $m): bool => $m === RedirectToAgent::class))->toHaveCount(1)
        ->and(app('router')->getMiddlewareGroups()['chat'])->not->toContain(RedirectToAgent::class);
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

it('memoizes the hosted-chat agent lookup per request', function (): void {
    $agent = Agent::factory()->create();
    $request = Request::create("https://chatvia.test/chat?agent={$agent->external_id}");

    $first = HostedChatAgent::from($request);

    DB::enableQueryLog();

    expect(HostedChatAgent::from($request))->toBe($first)
        ->and($first?->is($agent))->toBeTrue()
        ->and(DB::getQueryLog())->toBe([]);
});

it('resolves no hosted-chat agent when the request names none, even if an agent has no subdomain', function (): void {
    // Regression: a request without an agent slug looked agents up by a NULL
    // slug, which matched any agent whose sub_domain is NULL.
    Agent::factory()->create(['sub_domain' => null]);

    $request = Request::create('http://localhost/chat');
    $request->setLaravelSession(app('session.store'));

    expect(HostedChatAgent::from($request))->toBeNull();

    // The middleware shows the not-found page instead of serving that agent.
    $response = app(EnsureChatIsAccessible::class)->handle($request, fn (): Response => new Response('next'));

    expect($response)->toBeInstanceOf(InertiaResponse::class);
});

it('no longer registers routes that render missing pages', function (string $name): void {
    expect(Route::has($name))->toBeFalse();
})->with(['knowledge-base.show', 'billing.payment-methods.create']);
