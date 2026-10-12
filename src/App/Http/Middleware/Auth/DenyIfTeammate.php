<?php

namespace App\Http\Middleware\Auth;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Keeps teammates out of owner-only areas such as workspace management:
 * they are sent back to the dashboard. Every staff account is either its
 * workspace's owner or a teammate, so this admits owners only.
 */
class DenyIfTeammate
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()?->isTeammate()) {
            return redirect()->route('dashboard');
        }

        return $next($request);
    }
}
