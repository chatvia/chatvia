<?php

use App\Http\Middleware\Chat\EnsureChatIsAccessible;
use App\Http\Middleware\Chat\RedirectToAgent;
use Domain\Agent\Models\Agent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
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

    $first = current_agent_from_widget();

    expect(current_agent_from_widget())->toBe($first)
        ->and($first?->is($agent))->toBeTrue();
});

it('no longer registers routes that render missing pages', function (string $name): void {
    expect(Route::has($name))->toBeFalse();
})->with(['knowledge-base.show', 'billing.payment-methods.create']);
