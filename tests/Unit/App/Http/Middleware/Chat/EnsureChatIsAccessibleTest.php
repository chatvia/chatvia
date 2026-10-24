<?php

use App\Http\Middleware\Chat\EnsureChatIsAccessible;
use Illuminate\Http\Request;
use Inertia\Response as InertiaResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * The access check serves a chat only for the agent resolved before it; a
 * request without one gets the not-found page instead of reaching the chat.
 */
it('shows the not-found page when no agent was resolved for the request', function (): void {
    $request = Request::create('http://localhost/chat');
    $request->setLaravelSession(app('session.store'));

    $response = app(EnsureChatIsAccessible::class)->handle($request, fn (): Response => new Response('next'));

    expect($response)->toBeInstanceOf(InertiaResponse::class);
});
