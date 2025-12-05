<?php

namespace App\Http\Middleware;

use App\Features\Actions;
use App\Features\Widget;
use App\Features\Workflow;
use App\Models\Team;
use App\Services\Teams\TeamService;
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
            'subscribed' => $request->user()?->subscribed(),
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
            'allTeams' => $request->user('web') ? $this->getAllTeams($request) : [],
        ];
    }

    private function getCurrentTeam(Request $request): ?array
    {
        $user = $request->user('web');

        if (! $user) {
            return null;
        }

        // Check if there's a team in session
        $currentTeamId = session('current_team_id');

        if ($currentTeamId) {
            $team = Team::find($currentTeamId);

            // Verify user still has access to this team
            if ($team && ($user->ownsTeam($team) || $user->belongsToTeam($team))) {
                return [
                    'id' => $team->id,
                    'name' => $team->name,
                    'is_owner' => $user->ownsTeam($team),
                    'is_primary' => $team->id === $user->primaryTeam()?->id,
                ];
            }
        }

        // Fall back to primary team
        $primaryTeam = $user->primaryTeam();

        if (! $primaryTeam) {
            return null;
        }

        // Store in session for next time
        session(['current_team_id' => $primaryTeam->id]);

        return [
            'id' => $primaryTeam->id,
            'name' => $primaryTeam->name,
            'is_owner' => $user->ownsTeam($primaryTeam),
            'is_primary' => true,
        ];
    }

    private function getAllTeams(Request $request): array
    {
        $user = $request->user('web');

        if (! $user) {
            return [];
        }

        // Get owned teams
        $ownedTeams = $user->ownedTeams()->get()->map(fn ($team) => [
            'id' => $team->id,
            'name' => $team->name,
            'is_owner' => true,
            'is_primary' => $team->id === $user->primaryTeam()?->id,
        ]);

        // Get teams where user is a member
        $memberTeams = $user->teams()->get()->map(fn ($team) => [
            'id' => $team->id,
            'name' => $team->name,
            'is_owner' => false,
            'is_primary' => false,
        ]);

        return $ownedTeams->merge($memberTeams)->values()->all();
    }

    private function getTeamPermissions(Request $request): array
    {
        $user = $request->user('web');

        if (! $user) {
            return [];
        }

        return app(TeamService::class)->getUserPermissionsForInertia($user);
    }

    private function subDomain(string $host): ?string
    {
        $appUrl = parse_url(
            url: config('app.url'),
            component: PHP_URL_HOST
        );

        $subdomain = null;

        if ($host !== $appUrl && ! filter_var($host, FILTER_VALIDATE_IP)) {
            $hostParts = explode('.', $host);
            $appUrlParts = explode('.', $appUrl);

            if (count($hostParts) > count($appUrlParts)) {
                $subdomain = $hostParts[0];
            }
        }

        return $subdomain;
    }

    private function transformModel(array $model): array
    {
        return [
            'id' => $model['external_name'],
            'external_id' => $model['external_name'],
            'name' => $model['descriptive_name'],
            'context' => $model['context_window'],
            'icon' => $model['icon'],
            'description' => $model['description'],
            'features' => $model['features'] ?? [],
        ];
    }
}
