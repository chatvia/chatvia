<?php

namespace App\Admin;

use App\Identity\Workspace\Models\User;
use Illuminate\Contracts\Auth\Factory as AuthFactory;

/**
 * Who may open the staff tools — Horizon, Telescope, Pulse and Spectra: an
 * active admin signed in to the admin panel's own `admin` guard who has set up
 * two-factor authentication.
 *
 * A session on the shared `web` guard does not count. The dashboard and
 * hosted-chat logins have no two-factor step for admins, so only the panel
 * login (which asks for the authenticator code) can open these tools.
 */
final readonly class StaffToolsAccess
{
    public function __construct(
        private AuthFactory $auth,
    ) {}

    public function allows(): bool
    {
        $staff = $this->auth->guard('admin')->user();

        return $staff instanceof User
            && $staff->isAdmin()
            && ! $staff->isDeactivated()
            && filled($staff->getAppAuthenticationSecret());
    }
}
