<?php

use App\Identity\Workspace\Models\User;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Route as Router;
use Illuminate\Support\Str;

/*
|--------------------------------------------------------------------------
| An unverified e-mail address opens nothing of the workspace
|--------------------------------------------------------------------------
|
| Sign-up signs the new owner in before the address is verified. Until it
| is, the dashboard, the workspace and account pages and the REST API stay
| closed: a made-up address must not be able to invite people (mail sent in
| the install's name) or mint API keys. Every signed-in route under `my/` and
| `api/v1/` carries `verified`.
*/

it('puts the verified gate on every workspace and API route', function (): void {
    $missing = collect(Router::getRoutes()->getRoutes())
        ->filter(fn (Route $route): bool => Str::startsWith($route->uri(), ['my/', 'api/v1/']))
        ->filter(fn (Route $route): bool => collect($route->gatherMiddleware())->contains(fn (string $middleware): bool => $middleware === 'auth' || Str::startsWith($middleware, 'auth:')))
        ->reject(fn (Route $route): bool => in_array('verified', $route->gatherMiddleware(), true))
        ->map(fn (Route $route): string => $route->uri())
        ->values()
        ->all();

    expect($missing)->toBe([]);
});

it('sends an unverified owner to the verification notice instead of a workspace page', function (): void {
    $owner = User::factory()->unverified()->create();
    createAgent($owner);

    $this->actingAs($owner)
        ->get('/my/workspace/people/chatters')
        ->assertRedirect(route('verification.notice'));
});

it('refuses an unverified owner the REST API', function (): void {
    withLicense();
    $owner = User::factory()->unverified()->create();
    createAgent($owner);

    $this->withToken($owner->createToken('account')->plainTextToken)
        ->getJson('/api/v1/agents')
        ->assertForbidden();
});
