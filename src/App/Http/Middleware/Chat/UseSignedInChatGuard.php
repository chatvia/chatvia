<?php

declare(strict_types=1);

namespace App\Http\Middleware\Chat;

use App\Http\Chat\HostedChatViewer;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * First step of the `chat` middleware group: when staff (not a chatter) are
 * signed in to the hosted chat, their `chat_staff` guard becomes the request's
 * default, so everything after reads the viewer from `$request->user()`.
 */
final class UseSignedInChatGuard
{
    public function handle(Request $request, Closure $next): Response
    {
        HostedChatViewer::useSignedInGuard();

        return $next($request);
    }
}
