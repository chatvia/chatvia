<?php

declare(strict_types=1);

namespace App\Http\Middleware\Access;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Guards an owner's own-account page (`account.option:data_export` or
 * `account.option:deletion`) behind its switch in `chatvia.account` (admin
 * panel, Settings → Workspaces → Owner account). While it is off the routes
 * answer 404, as if they did not exist.
 */
class EnsureAccountOptionIsOn
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, string $option): Response
    {
        abort_unless((bool) (config("chatvia.account.{$option}") ?? true), 404);

        return $next($request);
    }
}
