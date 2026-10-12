<?php

namespace App\Http\Middleware\Chat;

use App\Http\Chat\HostedChatViewer;
use App\Http\Middleware\Web\HandleInertiaRequests;
use App\Identity\Chatter\Models\Chatter;
use Illuminate\Http\Request;
use Inertia\Middleware;

/**
 * Inertia root for the hosted chat (`chat` group): the props every chat page
 * shares — product branding, the signed-in user, the current route and the
 * per-chat upload limit. The dashboard has its own root middleware
 * ({@see HandleInertiaRequests}), which also runs on chat pages.
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
        $account = HostedChatViewer::account();

        return [
            ...parent::share($request),
            'name' => config('app.name'),
            // Product name and links (config/chatvia.php `brand`) — drives the
            // "Powered by" credit under the hosted chat.
            'brand' => config('chatvia.brand'),
            'auth' => [
                'user' => $account,
                // Staff (a dashboard preview) chat too, but their profile is
                // edited on the dashboard, not here.
                'is_chatter' => $account instanceof Chatter,
            ],
            // The matched route's name and raw parameters, for active-state
            // checks (useCurrentRoute); URLs themselves come from Wayfinder.
            'route' => [
                'name' => $request->route()?->getName(),
                // An object even when empty, so the page gets {} rather than [].
                'params' => (object) ($request->route()?->originalParameters() ?? []),
            ],
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
            'schema' => $request->getScheme(),
            'environment' => app()->environment(),
            // Per-chat upload ceiling (config: agent.files.upload.max_files).
            // The composer gates the paperclip on this; the server enforces
            // the same value via MaxConversationAttachments so a frontend
            // bypass still gets a 422.
            'maxFilesPerChat' => (int) config('chatvia.agent.files.upload.max_files'),
        ];
    }
}
