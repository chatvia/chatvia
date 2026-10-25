<?php

namespace App\Http\Middleware\Access;

use App\Identity\Workspace\Models\User;
use Closure;
use Domain\Security\Data\NetworkRulesData;
use Domain\Security\Enums\GeoMode;
use Domain\Security\Models\WorkspaceSecurityPolicy;
use Domain\Security\Services\NetworkAccessGuard;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Support\License\Entitlements;
use Symfony\Component\HttpFoundation\Response;

/**
 * Enforces a workspace's network-access policy (IP allowlist + geo) on the
 * authenticated dashboard and the public API. Appended to the `web` group and
 * set on the authenticated `/api/v1` group, so it runs on every such request
 * but no-ops unless ALL of: there's an authenticated user (a session, or the
 * owner of the API bearer token), their workspace has a policy row, and at
 * least one control (IP allowlist or geo) is on.
 *
 * Anti-lockout lives at the write boundary — the security-policy FormRequest
 * rejects rules that would block the saver — so a workspace can't fence itself
 * out via the UI. Platform admins get no bypass: on the dashboard they only
 * reach their own workspace, so its rules apply to them like anyone else.
 *
 * Scope: the web/Inertia dashboard surface (full page loads, Inertia partial
 * reloads, the dashboard broadcasting/auth endpoint) and every authenticated
 * `/api/v1` call, so a bearer token used from a blocked network is refused
 * like a dashboard session. Agents' chats are not covered: visitors chat from
 * anywhere.
 *
 * Network rules are an Enterprise feature. Without an Enterprise key they are
 * not enforced (it fails open, so a lapsed key locks nobody out), and the
 * saved rules apply again once a key is added.
 */
class EnsureWorkspaceNetworkAllowed
{
    public function __construct(
        private readonly NetworkAccessGuard $guard,
        private readonly Entitlements $entitlements,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        // On the API the person is the owner of the bearer token, which only
        // the `sanctum` guard knows when this runs before `auth:sanctum`.
        $user = $request->user() ?? $request->user('sanctum');

        if (! $user instanceof User || ! $this->entitlements->enterprise()) {
            return $next($request);
        }

        $rules = $this->networkRules($user->workspace()->id);

        if (! $rules instanceof NetworkRulesData) {
            return $next($request);
        }

        $allowed = $this->guard->passes(
            $rules->enforcedIpAllowlist(),
            $rules->geoMode,
            $rules->geoCountries,
            (string) $request->ip(),
            $rules->geoMode === GeoMode::Off ? null : $request->country(),
        );

        abort_if(! $allowed, 403, __('Access from your network is not permitted by this workspace.'));

        return $next($request);
    }

    /**
     * The workspace's network rules, or null when no control is on. Cached
     * because this runs on every web request; the model clears the entry on
     * every save (see WorkspaceSecurityPolicy::booted()).
     */
    private function networkRules(int $workspaceId): ?NetworkRulesData
    {
        // An empty array (not null) marks "nothing to enforce", so the common
        // case is cached too — Cache::remember() treats null as a miss.
        $cached = Cache::remember(
            WorkspaceSecurityPolicy::networkRulesCacheKey($workspaceId),
            now()->addMinutes(10),
            function () use ($workspaceId): array {
                $policy = WorkspaceSecurityPolicy::query()
                    ->where('workspace_id', $workspaceId)
                    ->first(['ip_allowlist_mode', 'ip_allowlist', 'geo_mode', 'geo_countries']);

                $rules = $policy === null ? null : NetworkRulesData::fromPolicy($policy);

                return $rules?->isActive() ? $rules->toCache() : [];
            },
        );

        return $cached === [] ? null : NetworkRulesData::fromCache($cached);
    }
}
