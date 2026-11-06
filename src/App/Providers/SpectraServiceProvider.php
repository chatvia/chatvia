<?php

declare(strict_types=1);

namespace App\Providers;

use App\Admin\Issue\Services\IssueGuzzleMiddleware;
use App\Admin\Settings\ToolRecording;
use App\Admin\StaffToolsAccess;
use App\Identity\Workspace\Models\User;
use App\Identity\Workspace\Services\WorkspaceDashboardViewers;
use Domain\Llm\Services\WorkspaceAttributedSpectra;
use GuzzleHttp\Client;
use GuzzleHttp\HandlerStack;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Spectra\Contracts\DashboardTrackableResolver;
use Spectra\Spectra;

/**
 * Spectra (the AI call log at /spectra): who may open it — staff signed in
 * to the admin panel ({@see StaffToolsAccess}), like Horizon and Pulse — whose
 * calls it records, and how it sees the streamed replies. Spectra's screens
 * show every workspace's calls; a workspace's owner gets a dashboard of their
 * own workspace instead ({@see WorkspaceDashboardViewers}).
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
    #[\Override]
    public function register(): void
    {
        $this->app->scoped(Spectra::class, fn (Application $app): Spectra => new WorkspaceAttributedSpectra($app));
        $this->app->bind(DashboardTrackableResolver::class, WorkspaceDashboardViewers::class);
    }

    public function boot(): void
    {
        // Both ask for an admin-panel session: Spectra is install-wide, so a
        // dashboard (web-guard) session never opens it. `manageSpectra` covers
        // designing dashboards and the Maintenance screen's actions.
        Gate::define('viewSpectra', fn (?User $user = null): bool => $this->app->make(StaffToolsAccess::class)->allows());
        Gate::define('manageSpectra', fn (?User $user = null): bool => $this->app->make(StaffToolsAccess::class)->allows());

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
