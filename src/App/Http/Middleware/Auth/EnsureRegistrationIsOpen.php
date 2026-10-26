<?php

namespace App\Http\Middleware\Auth;

use App\Identity\Workspace\Services\WorkspaceAllowance;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Guards the self-serve sign-up form: open while
 * `chatvia.registration_enabled` is on (the default; admin panel, Settings →
 * Workspaces) and the install may hold another workspace (without a license
 * key it holds one). A closed form answers 404.
 */
class EnsureRegistrationIsOpen
{
    public function __construct(private readonly WorkspaceAllowance $workspaces) {}

    public function handle(Request $request, Closure $next): Response
    {
        abort_unless($this->workspaces->signUpOpen(), 404);

        return $next($request);
    }
}
