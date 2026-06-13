<?php

namespace App\Http\Middleware\Access;

use App\Identity\Workspace\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureApiWorkspaceOwner
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user instanceof User) {
            abort(401);
        }

        // isWorkspace() === UserType::{Workspace, Admin} — i.e. not an employee
        // or chatter token.
        abort_unless($user->isWorkspace(), 403, 'This action requires workspace owner access.');

        return $next($request);
    }
}
