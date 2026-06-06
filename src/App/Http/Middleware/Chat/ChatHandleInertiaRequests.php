<?php

namespace App\Http\Middleware\Chat;

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
        $user = $request->user();

        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'user' => $user,
                'is_chatter' => $user?->isChatter() ?? false,
            ],
            'ziggy' => [
                ...(new Ziggy)->toArray(),
                'location' => $request->url(),
            ],
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
            //            'chatSession' => app(Session::class)->firstOrCreateSession(),
            'schema' => $request->getScheme(),
            'environment' => app()->environment(),
            'domain' => $request->domain(),
            'subdomain' => $request->subdomain(),
            // Per-chat upload ceiling (config: agent.files.upload.max_files).
            // The composer gates the paperclip on this; the server enforces
            // the same value via MaxConversationAttachments so a frontend
            // bypass still gets a 422.
            'maxFilesPerChat' => (int) config('chatvia.agent.files.upload.max_files'),
        ];
    }
}
