<?php

namespace App\Http\Middleware;

use App\Features\Actions;
use App\Features\Widget;
use App\Features\Workflow;
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
            'schema' => $request->getScheme(),
            'environment' => app()->environment(),

            'domain' => $request->domain(),
            'subdomain' => $request->subdomain(),
            'features' => [
                'widget' => Feature::active(Widget::class),
                'workflow' => Feature::active(Workflow::class),
                'actions' => Feature::active(Actions::class),
            ],
            'permissions' => $request->user('web')?->permissionsKeyValue() ?? [],
        ];
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
