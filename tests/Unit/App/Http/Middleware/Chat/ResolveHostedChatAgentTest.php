<?php

use App\Http\Chat\HostedChatAgent;
use App\Http\Middleware\Chat\ResolveHostedChatAgent;
use Domain\Agent\Models\Agent;
use Illuminate\Http\Request;
use Inertia\Response as InertiaResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * A request that names no agent gets the not-found page, never an agent:
 * looking agents up by an empty slug would match any agent that has none.
 */
it('shows the not-found page for a request without an agent slug, even when an agent has no slug', function (): void {
    Agent::factory()->create(['slug' => null]);
    $request = Request::create('http://localhost/chat');

    $response = app(ResolveHostedChatAgent::class)->handle($request, fn (): Response => new Response('next'));

    expect($response)->toBeInstanceOf(InertiaResponse::class)
        ->and(HostedChatAgent::from($request))->toBeNull();
});
