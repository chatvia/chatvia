<?php

declare(strict_types=1);

namespace App\Http\Middleware\Chat;

use App\Http\Chat\HostedChatViewer;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Session\Middleware\AuthenticateSession;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * First step of the `chat` middleware group: when staff (not a chatter) are
 * signed in to the hosted chat, their `chat_staff` guard becomes the request's
 * default, so everything after reads the viewer from `$request->user()`.
 *
 * Staff then get the same session check the `web` group runs (it ran while
 * `chat` was still the default guard, so it saw no one): a staff session
 * whose password changed since it signed in is ended here. Laravel keeps only
 * one AuthenticateSession per route, so it is called from here rather than
 * listed again in the group.
 */
final readonly class UseSignedInChatGuard
{
    public function __construct(
        private AuthenticateSession $sessions,
    ) {}

    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        HostedChatViewer::useSignedInGuard();

        if (Auth::getDefaultDriver() === HostedChatViewer::STAFF_GUARD) {
            return $this->sessions->handle($request, $next);
        }

        return $next($request);
    }
}
