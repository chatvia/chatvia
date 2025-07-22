<?php

namespace App\Http\Middleware\Billing;

use App\Services\Billing\QuotaService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class CheckQuota
{
    public function __construct(
        private QuotaService $quotaService
    ) {}

    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, string $quotaType, int $amount = 1): Response
    {
        $user = Auth::user();

        if (! $user) {
            return $next($request);
        }

        // Check if user has remaining quota
        if (! $this->quotaService->hasQuotaRemaining($user, $quotaType, $amount)) {

            // For API requests, return JSON error
            if ($request->expectsJson()) {
                $quota = $this->quotaService->getUserQuota($user);
                $used = $quota->getAttribute("{$quotaType}_used");
                $limit = $quota->getAttribute("{$quotaType}_limit");

                return response()->json([
                    'error' => 'Quota exceeded',
                    'message' => "You have exceeded your {$quotaType} quota.",
                    'quota' => [
                        'type' => $quotaType,
                        'used' => $used,
                        'limit' => $limit,
                        'remaining' => max(0, $limit - $used),
                    ],
                    'upgrade_url' => route('billing.index'),
                ], 429); // 429 Too Many Requests
            }

            // For web requests, redirect with error
            return redirect()->back()->with('error',
                "You have exceeded your {$quotaType} quota. Please upgrade your plan to continue."
            );
        }

        return $next($request);
    }
}
