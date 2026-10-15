<?php

declare(strict_types=1);

namespace App\Providers;

use App\Admin\Settings\ToolRecording;
use App\Admin\StaffToolsAccess;
use App\Identity\Workspace\Models\User;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

/**
 * Spectra (the AI call log at /spectra): who may open it — staff signed in
 * to the admin panel ({@see StaffToolsAccess}), like Horizon and Pulse — and
 * how it sees the streamed replies.
 *
 * Spectra watches Laravel's HTTP client by itself. A streamed reply goes out
 * through a plain Guzzle client from the container instead, so that client
 * gets Spectra's handler stack, which reads the stream as the reply is read.
 * Whether anything is recorded is the admin switch
 * ({@see ToolRecording}).
 */
class SpectraServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // The web-guard user Spectra passes in is ignored: only the admin
        // panel's own session counts.
        Gate::define('viewSpectra', fn (?User $user = null): bool => $this->app->make(StaffToolsAccess::class)->allows());

        // Spectra binds its handler only when it booted installed
        // (SPECTRA_ENABLED) with its watchers on.
        if ($this->app->bound('spectra.guzzle.handler')) {
            $this->app->bind(Client::class, fn (): Client => new Client(['handler' => $this->app->make('spectra.guzzle.handler')]));
        }
    }
}
