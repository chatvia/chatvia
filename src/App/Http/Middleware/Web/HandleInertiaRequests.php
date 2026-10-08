<?php

namespace App\Http\Middleware\Web;

use Domain\Membership\Team\Services\TeamService;
use Illuminate\Http\Request;
use Inertia\Middleware;
use Laravel\Pennant\Feature;
use Tighten\Ziggy\Ziggy;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    public function share(Request $request): array
    {
        $user = $request->user('web');

        if ($user) {
            // One round-trip for the whole billing graph so the share() fields
            // and the User Appends accessors all hit the same in-memory data.
            $user->loadMissing(['parentWorkspace.billing.subscriptions', 'billing.subscriptions']);
        }

        return [
            ...parent::share($request),
            'name' => config('app.name'),
            // Product name and links (config/chatvia.php `brand`). A self-hosted
            // install sets its own; an empty URL hides the link that uses it.
            'brand' => config('chatvia.brand'),
            'auth' => [
                'user' => $user,
            ],
            'ziggy' => [
                ...(new Ziggy)->toArray(),
                'location' => $request->url(),
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
            'subscribed' => (bool) $user?->hasActiveSubscription(),
            // Off on a self-hosted install without billing: the UI then hides
            // plans, upgrade prompts and billing links (useBilling()).
            'billing' => [
                'enabled' => (bool) config('billing.enabled', true),
            ],
            'schema' => $request->getScheme(),
            'environment' => app()->environment(),

            'domain' => $request->domain(),
            'subdomain' => $request->subdomain(),
            'permissions' => $this->getTeamPermissions($request),
            'currentTeam' => $user ? $this->getCurrentTeam($request) : null,
            // Pennant feature flags (entitlement-derived) plus the install-wide
            // switches for unfinished features (`chatvia.features`). Frontend
            // gates via `useFeature().hasFeature('security')`; resolved per
            // request so they always reflect the workspace's current plan.
            'features' => $user ? [
                ...Feature::all(),
                'youtube_sources' => (bool) config('chatvia.features.youtube_sources', false),
            ] : [],
        ];
    }

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

    private function getTeamPermissions(Request $request): array
    {
        $user = $request->user('web');

        if (! $user) {
            return [];
        }

        return app(TeamService::class)->userPermissionsMap($user);
    }
}
