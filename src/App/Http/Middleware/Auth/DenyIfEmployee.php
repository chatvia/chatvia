<?php

namespace App\Http\Middleware\Auth;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Keeps teammates (employee accounts) out of owner-only areas such as billing
 * and workspace management: they are sent back to the dashboard.
 */
class DenyIfEmployee
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()?->isEmployee()) {
            return redirect()->route('dashboard');
        }

        return $next($request);
    }
}
