<?php

use App\Admin\StaffToolsAccess;
use App\Identity\Workspace\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

/*
 * Horizon and Spectra open only for staff signed in to the
 * admin panel (its own `admin` guard, with two-factor set up). A dashboard
 * session on the shared `web` guard is not enough, because the dashboard and
 * hosted-chat logins have no two-factor step for admins.
 */

const STAFF_TOOL_GATES = ['viewHorizon', 'viewSpectra'];

function staffAdmin(bool $withTwoFactor = true): User
{
    $admin = User::factory()->admin()->create();

    if ($withTwoFactor) {
        $admin->saveAppAuthenticationSecret('JBSWY3DPEHPK3PXP');
    }

    return $admin->fresh();
}

it('refuses an admin signed in on the web guard only', function (string $gate): void {
    $this->actingAs(staffAdmin(), 'web');

    expect(Gate::allows($gate))->toBeFalse();
})->with(STAFF_TOOL_GATES);

it('allows an admin signed in to the admin panel with two-factor set up', function (string $gate): void {
    Auth::guard('admin')->login(staffAdmin());

    expect(Gate::allows($gate))->toBeTrue();
})->with(STAFF_TOOL_GATES);

it('refuses a panel session without two-factor, a deactivated admin and a non-admin', function (): void {
    Auth::guard('admin')->login(staffAdmin(withTwoFactor: false));
    expect(app(StaffToolsAccess::class)->allows())->toBeFalse();

    $deactivated = staffAdmin();
    $deactivated->update(['deactivated_at' => now()]);
    Auth::guard('admin')->login($deactivated);
    expect(app(StaffToolsAccess::class)->allows())->toBeFalse();

    $owner = User::factory()->workspace()->create();
    $owner->saveAppAuthenticationSecret('JBSWY3DPEHPK3PXP');
    Auth::guard('admin')->login($owner);
    expect(app(StaffToolsAccess::class)->allows())->toBeFalse();
});

it('skips two-factor on a local machine but still needs an active admin on the panel guard', function (): void {
    $this->app['env'] = 'local';

    Auth::guard('admin')->login(staffAdmin(withTwoFactor: false));
    expect(app(StaffToolsAccess::class)->allows())->toBeTrue();

    $deactivated = staffAdmin(withTwoFactor: false);
    $deactivated->update(['deactivated_at' => now()]);
    Auth::guard('admin')->login($deactivated);
    expect(app(StaffToolsAccess::class)->allows())->toBeFalse();

    Auth::guard('admin')->logout();
    $this->actingAs(staffAdmin(withTwoFactor: false), 'web');
    expect(app(StaffToolsAccess::class)->allows())->toBeFalse();
});
