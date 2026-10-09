<?php

namespace App\Http\Middleware\Access;

use App\Identity\Workspace\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Public API gate (`api.owner`) for account-level operations only the
 * workspace owner may run (for example managing teams and roles). Teammate
 * and chatter tokens get a 403; admins pass.
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

        // isWorkspace() is true for workspace owners and admins only.
        abort_unless($user->isWorkspace(), Response::HTTP_FORBIDDEN, __('This action requires workspace owner access.'));

        return $next($request);
    }
}
