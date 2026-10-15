<?php

declare(strict_types=1);

namespace App\Admin;

use App\Identity\Workspace\Models\User;
use Illuminate\Contracts\Auth\Factory as AuthFactory;

/**
 * Who may open the staff tools (Horizon, Pulse, Spectra and the admin
 * panel's Logs page): an active admin signed in to the admin panel's own
 * `admin` guard.
 *
 * A session on the shared `web` guard does not count, so only the panel login
 * (which asks for the authenticator code when one is set up) can open these
 * tools.
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
            && ! $staff->isDeactivated();
    }
}
