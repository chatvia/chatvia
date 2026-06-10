<?php

namespace App\Http\Middleware\Web;

use Domain\Membership\Team\Services\TeamService;
use Illuminate\Http\Request;
use Inertia\Middleware;
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
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
            'subscribed' => (bool) $user?->hasActiveSubscription(),
            'schema' => $request->getScheme(),
            'environment' => app()->environment(),

            'domain' => $request->domain(),
            'subdomain' => $request->subdomain(),
            'permissions' => $this->getTeamPermissions($request),
            'currentTeam' => $user ? $this->getCurrentTeam($request) : null,
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
