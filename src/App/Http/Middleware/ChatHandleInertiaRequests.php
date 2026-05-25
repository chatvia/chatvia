<?php

namespace App\Http\Middleware;

use Domain\Chat\Services\Memory\ChatSession;
use Illuminate\Http\Request;
use Inertia\Middleware;
use Tighten\Ziggy\Ziggy;

class ChatHandleInertiaRequests extends Middleware
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
            //            'chatSession' => app(ChatSession::class)->firstOrCreateChatSession(),
            'schema' => $request->getScheme(),
            'environment' => app()->environment(),
            'domain' => $request->domain(),
            'subdomain' => $request->subdomain(),
        ];
    }
}
