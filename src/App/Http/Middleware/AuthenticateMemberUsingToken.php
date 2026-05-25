<?php

namespace App\Http\Middleware;

use App\Auth\Services\AgentMemberAuthTokenService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateMemberUsingToken
{
    public function __construct(
        private readonly AgentMemberAuthTokenService $authTokenService
    ) {}

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->filled('auth_token')) {
            $authToken = $request->input('auth_token');

            if ($cachedAuthToken = $this->authTokenService->getTokenData($authToken)) {
                Auth::guard('web')->loginUsingId($cachedAuthToken['user_id']);

                $this->authTokenService->invalidateToken($authToken);

                return redirect()->route('chat.home');
            }
        }

        return $next($request);
    }
}
