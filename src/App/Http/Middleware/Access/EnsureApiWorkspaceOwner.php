<?php

namespace App\Http\Middleware\Access;

use App\Identity\Workspace\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Public API gate (`api.owner`) for account-level operations only the
 * workspace owner may run (for example managing teams and roles). Any other
 * token — a teammate's included — gets a 403.
 */
class EnsureApiWorkspaceOwner
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user instanceof User) {
            abort(Response::HTTP_UNAUTHORIZED);
        }

        abort_unless($user->isWorkspaceOwner(), Response::HTTP_FORBIDDEN, __('This action requires workspace owner access.'));

        return $next($request);
    }
}
