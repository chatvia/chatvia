<?php

declare(strict_types=1);

namespace App\Providers;

use App\Admin\Issue\Services\IssueGuzzleMiddleware;
use App\Admin\Settings\ToolRecording;
use App\Admin\StaffToolsAccess;
use App\Identity\Workspace\Models\User;
use GuzzleHttp\Client;
use GuzzleHttp\HandlerStack;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

/**
 * Spectra (the AI call log at /spectra): who may open it — staff signed in
 * to the admin panel ({@see StaffToolsAccess}), like Horizon and Pulse, or
 * the owner of a workspace an admin granted Spectra access — and how it sees
 * the streamed replies.
 *
 * Spectra watches Laravel's HTTP client by itself. A streamed reply goes out
 * through a plain Guzzle client from the container instead, so that client
 * gets Spectra's handler stack, which reads the stream as the reply is read,
 * and the Issues check ({@see IssueGuzzleMiddleware}), which sees a refused
 * reply the way it sees refused HTTP-client calls.
 * Whether anything is recorded is the admin switch
 * ({@see ToolRecording}).
 */
class SpectraServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // An admin-panel session always may; a dashboard (web-guard) session
        // only when it is the owner of a workspace staff granted Spectra
        // access in the admin panel. Spectra is install-wide, so that grant
        // shows every workspace's AI calls: it is off by default and never
        // reaches the workspace's teammates.
        Gate::define('viewSpectra', fn (?User $user = null): bool => $this->app->make(StaffToolsAccess::class)->allows()
            || ($user?->isWorkspaceOwner() === true && ($user->workspaceMembership?->workspace?->spectra_access ?? false)));

        // The app's Guzzle client (the streamed chat replies) goes through
        // Spectra's handler when Spectra booted installed (SPECTRA_ENABLED)
        // with its watchers on, and always past the Issues check. The stack
        // is copied, so the check is added once per client, never to
        // Spectra's shared one.
        $this->app->bind(function (): Client {
            $stack = $this->app->bound('spectra.guzzle.handler') ? clone $this->app->make('spectra.guzzle.handler') : HandlerStack::create();
            $stack->push($this->app->make(IssueGuzzleMiddleware::class), 'issues');

            return new Client(['handler' => $stack]);
        });
    }
}
