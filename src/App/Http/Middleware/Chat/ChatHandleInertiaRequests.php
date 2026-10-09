<?php

namespace App\Http\Middleware\Chat;

use App\Http\Middleware\Web\HandleInertiaRequests;
use Illuminate\Http\Request;
use Inertia\Middleware;
use Tighten\Ziggy\Ziggy;

/**
 * Inertia root for the hosted chat (`chat` group): the props every chat page
 * shares — product branding, the signed-in user, Ziggy routes, the current
 * agent subdomain and the per-chat upload limit. The dashboard has its own
 * root middleware ({@see HandleInertiaRequests}).
 */
class ChatHandleInertiaRequests extends Middleware
{
    #[\Override]
    protected $rootView = 'app';

    /**
     * @return array<string, mixed>
     */
    #[\Override]
    public function share(Request $request): array
    {
        $user = $request->user();

        return [
            ...parent::share($request),
            'name' => config('app.name'),
            // Product name and links (config/chatvia.php `brand`) — drives the
            // "Powered by" credit under the hosted chat.
            'brand' => config('chatvia.brand'),
            'auth' => [
                'user' => $user,
                'is_chatter' => $user?->isChatter() ?? false,
            ],
            'ziggy' => [
                ...(new Ziggy)->toArray(),
                'location' => $request->url(),
            ],
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
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
