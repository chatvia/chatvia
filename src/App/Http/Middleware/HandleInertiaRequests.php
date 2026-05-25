<?php

namespace App\Http\Middleware;

use App\Features\Actions;
use App\Features\Widget;
use App\Features\Workflow;
use App\Teams\Models\Team;
use App\Teams\Services\TeamService;
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
        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'user' => $request->user('web'),
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
            ],
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
            'subscribed' => $request->user()?->hasActiveSubscription(),
            'onTrial' => $request->user()?->onTrial(),
            'trialEndsAt' => $request->user()?->onTrial() ? $request->user()->subscription()?->trial_ends_at?->format('F j, Y') : null,
            'subscriptionStartsAt' => $request->user()?->onTrial() ? $request->user()->subscription()?->trial_ends_at?->format('F j, Y') : null,
            'schema' => $request->getScheme(),
            'environment' => app()->environment(),

            'domain' => $request->domain(),
            'subdomain' => $request->subdomain(),
            'features' => [
                //                'widget' => Feature::active(Widget::class),
                //                'workflow' => Feature::active(Workflow::class),
                //                'actions' => Feature::active(Actions::class),
            ],
            'permissions' => $this->getTeamPermissions($request),
            'currentTeam' => $request->user('web') ? $this->getCurrentTeam($request) : null,
        ];
    }

    private function getCurrentTeam(Request $request): ?array
    {
        $user = $request->user('web');

        if (! $user) {
            return null;
        }

        // Get the current team from the database (ensures it's available in all contexts)
        $team = $user->ensureCurrentTeam();

        if (! $team) {
            return null;
        }

        return [
            'id' => $team->id,
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
