<?php

declare(strict_types=1);

namespace App\Admin;

use App\Identity\Workspace\Models\User;
use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Illuminate\Contracts\Foundation\Application;

/**
 * Who may open the staff tools — Horizon, Telescope, Pulse, Spectra and the
 * panel's Settings: an active admin signed in to the admin panel's own `admin`
 * guard who has set up two-factor authentication. On a local machine
 * (APP_ENV=local) the two-factor requirement is skipped.
 *
 * A session on the shared `web` guard does not count. The dashboard and
 * hosted-chat logins have no two-factor step for admins, so only the panel
 * login (which asks for the authenticator code) can open these tools.
 */
final readonly class StaffToolsAccess
{
    public function __construct(
        private AuthFactory $auth,
        private Application $app,
    ) {}

    public function allows(): bool
    {
        $staff = $this->auth->guard('admin')->user();

        return $staff instanceof User
            && $staff->isAdmin()
            && ! $staff->isDeactivated()
            && ($this->app->isLocal() || filled($staff->getAppAuthenticationSecret()));
    }
}
