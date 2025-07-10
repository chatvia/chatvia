<?php

namespace App\Http\Middleware;

use App\Support\ChatMemory;
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
        return [
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
            'defaultModel' => config('llms.0'),
            'chatSession' => app(ChatMemory::class)->getOrGenerateSession(),
            'models' => config('llms'),
            'domain' => $request->domain(),
            'subdomain' => $request->subdomain(),
            'subscribed' => $request->user()?->subscribed(),
            'onTrial' => $request->user()?->onTrial(),
            'schema' => $request->getScheme(),
            'quote' => $request->user()?->quote,
            'environment' => app()->environment(),
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
}
