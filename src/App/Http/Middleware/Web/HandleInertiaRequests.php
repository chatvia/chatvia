<?php

namespace App\Http\Middleware\Web;

use App\Http\Middleware\Chat\ChatHandleInertiaRequests;
use Domain\Membership\Team\Services\TeamService;
use Illuminate\Http\Request;
use Inertia\Middleware;

/**
 * Inertia root for the dashboard and the rest of the `web` group: the props
 * every page shares — the signed-in user, flash messages, locale, current
 * team and its permissions, and feature flags.
 * The hosted chat has its own root middleware
 * ({@see ChatHandleInertiaRequests}).
 */
class HandleInertiaRequests extends Middleware
{
    #[\Override]
    protected $rootView = 'app';

    /**
     * @return array<string, mixed>
     */
    #[\Override]
    public function share(Request $request): array
    {
        $user = $request->user('web');

        return [
            ...parent::share($request),
            'name' => config('app.name'),
            // Product name and links (config/chatvia.php `brand`). A self-hosted
            // install sets its own; an empty URL hides the link that uses it.
            'brand' => config('chatvia.brand'),
            'auth' => [
                'user' => $user,
            ],
            // The matched route's name and raw parameters, for active-state
            // checks (useCurrentRoute); URLs themselves come from Wayfinder.
            'route' => [
                'name' => $request->route()?->getName(),
                // An object even when empty, so the page gets {} rather than [].
                'params' => (object) ($request->route()?->originalParameters() ?? []),
            ],
            'flash' => [
                'success' => $request->session()->get('success'),
                'error' => $request->session()->get('error'),
                'warning' => $request->session()->get('warning'),
                'info' => $request->session()->get('info'),
                // One-shot payloads flashed by a controller — e.g. the signed
                // accept URL after an agent-member invitation is sent.
                'invitation' => $request->session()->get('invitation'),
            ],
            // Persisted UI language for the signed-in user. `auto` lets the
            // client fall back to the browser locale. app.ts syncs vue-i18n +
            // document dir/lang from this on every Inertia navigation.
            'locale' => $user?->locale ?? 'auto',
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
            'schema' => $request->getScheme(),
            'environment' => app()->environment(),

            'domain' => $request->domain(),
            'subdomain' => $request->subdomain(),
            'permissions' => $this->getTeamPermissions($request),
            'currentTeam' => $user ? $this->getCurrentTeam($request) : null,
            // Install-wide switches for unfinished features (`chatvia.features`).
            // Frontend gates via `useFeature().hasFeature('youtube_sources')`.
            'features' => $user ? [
                'youtube_sources' => (bool) config('chatvia.features.youtube_sources', false),
            ] : [],
        ];
    }

    /**
     * @return array{id: int, external_id: string, name: string, is_owner: bool, is_primary: bool}|null
     */
    private function getCurrentTeam(Request $request): ?array
    {
        $user = $request->user('web');

        if (! $user) {
            return null;
        }

        // Read-only resolve from the DB (no silent UPDATE on Inertia render).
        $team = $user->currentOrPrimaryTeam();

        if (! $team) {
            return null;
        }

        return [
            'id' => $team->id,
            'external_id' => $team->external_id,
            'name' => $team->name,
            'is_owner' => $user->ownsTeam($team),
            'is_primary' => $team->id === $user->primaryTeam()?->id,
        ];
    }

    /**
     * @return array<int, list<string>>
     */
    private function getTeamPermissions(Request $request): array
    {
        $user = $request->user('web');

        if (! $user) {
            return [];
        }

        return app(TeamService::class)->userPermissionsMap($user);
    }
}
