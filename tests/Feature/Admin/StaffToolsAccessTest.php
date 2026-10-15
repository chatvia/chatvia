<?php

use App\Admin\StaffToolsAccess;
use App\Identity\Workspace\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

/*
 * Horizon and Pulse open only for staff signed in to the admin panel (its
 * own `admin` guard). A dashboard session on the shared `web` guard is not
 * enough. Two-factor authentication is optional, as for the panel itself.
 */

const STAFF_TOOL_GATES = ['viewHorizon', 'viewPulse'];

function staffAdmin(): User
{
    return User::factory()->admin()->create()->fresh();
}

it('refuses an admin signed in on the web guard only', function (string $gate): void {
    $this->actingAs(staffAdmin(), 'web');

    expect(Gate::allows($gate))->toBeFalse();
})->with(STAFF_TOOL_GATES);

it('allows an admin signed in to the admin panel, with or without two-factor', function (string $gate): void {
    Auth::guard('admin')->login(staffAdmin());
    expect(Gate::allows($gate))->toBeTrue();

    $withTwoFactor = staffAdmin();
    $withTwoFactor->saveAppAuthenticationSecret('JBSWY3DPEHPK3PXP');
    Auth::guard('admin')->login($withTwoFactor);
    expect(Gate::allows($gate))->toBeTrue();
})->with(STAFF_TOOL_GATES);

it('refuses a deactivated admin and a non-admin on the panel guard', function (): void {
    $deactivated = staffAdmin();
    $deactivated->update(['deactivated_at' => now()]);
    Auth::guard('admin')->login($deactivated);
    expect(app(StaffToolsAccess::class)->allows())->toBeFalse();

    Auth::guard('admin')->login(User::factory()->workspace()->create());
    expect(app(StaffToolsAccess::class)->allows())->toBeFalse();
});
