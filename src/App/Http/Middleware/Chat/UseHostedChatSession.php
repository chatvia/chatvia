<?php

declare(strict_types=1);

namespace App\Http\Middleware\Chat;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gives the hosted chat its own session and sign-in. Global middleware, so it
 * runs before the `web` group starts the session.
 *
 * A request under /chat/ uses the chat session cookie (`session.chat`, sent
 * only to /chat) and the `chat` guard (staff signed in to a chat use
 * `chat_staff`; see {@see UseSignedInChatGuard}). Every other request uses the
 * dashboard's cookie and `web` guard. Sessions live in Redis, keyed by the
 * cookie's session id, so the two never share one; an install that picks
 * the database driver gets separate tables too (`chat_web_sessions`). So
 * signing in or out of a chat never changes the dashboard session, and the
 * reverse.
 *
 * Over HTTPS the chat cookie is SameSite=None, Secure and partitioned, so the
 * chat keeps its session inside an iframe on another site (the embed).
 *
 * Both sides are set on every request, not only for the chat: tests (and any
 * long-running process) serve several requests from one app instance.
 */
final class UseHostedChatSession
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $dashboard = $this->dashboardSettings();
        $isChat = $request->is('chat/*');

        $settings = $isChat ? [
            'cookie' => (string) config('session.chat.cookie'),
            'path' => (string) config('session.chat.path'),
            'secure' => $request->isSecure() ?: $dashboard['secure'],
            'same_site' => $request->isSecure() ? 'none' : $dashboard['same_site'],
            'partitioned' => $request->isSecure(),
            'table' => (string) config('session.chat.table'),
        ] : $dashboard;

        $tableChanged = config('session.table') !== $settings['table'];

        config([
            'session.cookie' => $settings['cookie'],
            'session.path' => $settings['path'],
            'session.secure' => $settings['secure'],
            'session.same_site' => $settings['same_site'],
            'session.partitioned' => $settings['partitioned'],
            'session.table' => $settings['table'],
        ]);

        // The database session driver binds its table when it is built. When
        // an app that already served a request switches between the chat and
        // the dashboard, rebuild it so it writes to the right table.
        if ($tableChanged && app()->resolved('session')) {
            app('session')->forgetDrivers();
            app()->forgetInstance('session.store');

            // The redirector keeps the store it was built with; hand it the
            // new one, or `intended()` and flashed data read the old table.
            if (app()->resolved('redirect')) {
                app('redirect')->setSession(app('session.store'));
            }
        }

        // The session store keeps the cookie name it was built with. It is
        // normally built later (StartSession reads the config above), but an
        // app that already served a request has one.
        if (app()->resolved('session.store')) {
            app('session.store')->setName($settings['cookie']);
        }

        // Cookies queued during the request (the remember-me cookie) take
        // their path and flags from here.
        app('cookie')->setDefaultPathAndDomain(
            $settings['path'],
            config('session.domain'),
            $settings['secure'],
            $settings['same_site'],
        );

        Auth::shouldUse($isChat ? 'chat' : 'web');

        return $next($request);
    }

    /**
     * The dashboard's session settings, saved on first use: the request above
     * changes the live `session.*` values.
     *
     * @return array{cookie: string, path: string, secure: bool|null, same_site: string|null, partitioned: bool, table: string}
     */
    private function dashboardSettings(): array
    {
        $saved = config('session.dashboard');

        if (is_array($saved)) {
            /** @var array{cookie: string, path: string, secure: bool|null, same_site: string|null, partitioned: bool, table: string} $saved */
            return $saved;
        }

        $saved = [
            'cookie' => (string) config('session.cookie'),
            'path' => (string) config('session.path'),
            'secure' => config('session.secure'),
            'same_site' => config('session.same_site'),
            'partitioned' => (bool) config('session.partitioned'),
            'table' => (string) config('session.table'),
        ];

        config(['session.dashboard' => $saved]);

        return $saved;
    }
}
