<?php

namespace App\Http\Middleware;

use App\Support\Chat\ChatMemory;
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
        $subDomain = $request->subdomain();

        $data = [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'user' => $request->user(),
            ],
            'ziggy' => [
                ...(new Ziggy)->toArray(),
                'location' => $request->url(),
            ],
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
            'chatSession' => app(ChatMemory::class)->getOrGenerateSession(),
            'subscribed' => $request->user()?->subscribed(),
            'onTrial' => $request->user()?->onTrial(),
            'schema' => $request->getScheme(),
            'quote' => $request->user()?->quote,
            'environment' => app()->environment(),

            'domain' => $request->domain(),
            'subdomain' => $request->subdomain(),
        ];

        if ($subDomain) {
            $data['models'] = collect(config('llms'))->map(fn ($model) => $this->transformModel($model))->toArray();
            //                'defaultModel' => $this->transformModel(config('llms.0')),

        }

        return $data;
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
