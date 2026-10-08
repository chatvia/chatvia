<?php

namespace App\Http\Middleware\Access;

use App\Identity\Workspace\Models\User;
use Closure;
use Domain\Security\Models\WorkspaceSecurityPolicy;
use Domain\Security\Services\NetworkAccessGuard;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

/**
 * Enforces a workspace's network-access policy (IP allowlist + geo) on the
 * authenticated dashboard. Appended to the `web` group, so it runs on every web
 * request but no-ops unless ALL of: there's an authenticated user, they aren't
 * platform staff, their workspace has a policy row, and at least one control
 * (IP allowlist or geo) is on.
 *
 * Anti-lockout lives at the write boundary — the security-policy FormRequest
 * rejects rules that would block the saver — so a workspace can't fence itself
 * out via the UI. Platform admins always bypass so support can reach any account.
 *
 * Scope: this guards the web/Inertia dashboard surface — full page loads,
 * Inertia partial reloads, and the dashboard broadcasting/auth endpoint (all in
 * the `web` group). The customer `/api/v1` surface lives in the `api` group and
 * is NOT covered yet, so a session-cookie-authenticated request from a blocked
 * country can still reach the same workspace data through the API.
 */
class EnsureWorkspaceNetworkAllowed
{
    public function __construct(private readonly NetworkAccessGuard $guard) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user instanceof User || $user->isAdmin()) {
            return $next($request);
        }

        $rules = $this->networkRules($user->workspace()->id);

        if ($rules === null) {
            return $next($request);
        }

        $ip = (string) $request->ip();
        $ipAllowlist = $rules['ip_allowlist_mode'] === 'off' ? [] : $rules['ip_allowlist'];
        $country = $rules['geo_mode'] === 'off' ? null : $request->country();

        $allowed = $this->guard->passes(
            $ipAllowlist,
            $rules['geo_mode'],
            $rules['geo_countries'],
            $ip,
            $country,
        );

        abort_if(! $allowed, 403, 'Access from your network is not permitted by this workspace.');

        return $next($request);
    }

    /**
     * The workspace's network rules, or null when no control is on. Cached
     * because this runs on every web request; the model clears the entry on
     * every save (see WorkspaceSecurityPolicy::booted()).
     *
     * @return array{ip_allowlist_mode: string, ip_allowlist: array<int, string>, geo_mode: string, geo_countries: array<int, string>}|null
     */
    private function networkRules(int $workspaceId): ?array
    {
        // An empty array (not null) marks "nothing to enforce", so the common
        // case is cached too — Cache::remember() treats null as a miss.
        $rules = Cache::remember(
            WorkspaceSecurityPolicy::networkRulesCacheKey($workspaceId),
            now()->addMinutes(10),
            function () use ($workspaceId): array {
                $policy = WorkspaceSecurityPolicy::query()
                    ->where('workspace_id', $workspaceId)
                    ->first(['ip_allowlist_mode', 'ip_allowlist', 'geo_mode', 'geo_countries']);

                if ($policy === null || ($policy->ip_allowlist_mode === 'off' && $policy->geo_mode === 'off')) {
                    return [];
                }

                return [
                    'ip_allowlist_mode' => $policy->ip_allowlist_mode,
                    'ip_allowlist' => $policy->ip_allowlist ?? [],
                    'geo_mode' => $policy->geo_mode,
                    'geo_countries' => $policy->geo_countries ?? [],
                ];
            },
        );

        return $rules === [] ? null : $rules;
    }
}
