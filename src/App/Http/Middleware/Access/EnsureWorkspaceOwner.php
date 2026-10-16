<?php

namespace App\Http\Middleware\Access;

use App\Identity\Workspace\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Dashboard gate (`workspace.owner`) for pages only the workspace owner may
 * open (account deletion, platform analytics). A teammate gets a 403.
 */
class EnsureWorkspaceOwner
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        abort_unless($user instanceof User && $user->isWorkspaceOwner(), Response::HTTP_FORBIDDEN);

        return $next($request);
    }
}
