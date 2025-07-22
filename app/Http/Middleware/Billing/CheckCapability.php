<?php

namespace App\Http\Middleware\Billing;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class CheckCapability
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, string $capability, $requiredValue = null): Response
    {
        $user = Auth::user();

        if (! $user) {
            return $this->handleUnauthorized($request);
        }

        // Check if user has the capability
        $hasCapability = $requiredValue !== null
            ? $user->capabilityAllows($capability, $requiredValue)
            : $user->hasCapability($capability);

        if (! $hasCapability) {
            return $this->handleInsufficientCapability($request, $capability, $requiredValue);
        }

        return $next($request);
    }

    private function handleUnauthorized(Request $request): Response
    {
        if ($request->expectsJson()) {
            return response()->json([
                'error' => 'Unauthorized',
                'message' => 'Authentication required.',
            ], 401);
        }

        return redirect()->route('login');
    }

    private function handleInsufficientCapability(Request $request, string $capability, $requiredValue = null): Response
    {
        $message = $requiredValue !== null
            ? "Your plan doesn't support {$capability} with the required level ({$requiredValue})."
            : "Your plan doesn't include {$capability} capability.";

        if ($request->expectsJson()) {
            return response()->json([
                'error' => 'Insufficient plan',
                'message' => $message,
                'capability' => $capability,
                'required_value' => $requiredValue,
                'upgrade_url' => route('billing.index'),
            ], 403);
        }

        return redirect()->back()->with('error', $message.' Please upgrade your plan to continue.');
    }
}
