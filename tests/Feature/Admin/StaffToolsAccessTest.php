<?php

use App\Admin\StaffToolsAccess;
use App\Identity\Workspace\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

/*
 * Horizon, Pulse and Spectra open only for staff signed in to the admin
 * panel (its own `admin` guard). A dashboard session on the shared `web`
 * guard is not enough. Two-factor authentication is optional, as for the
 * panel itself.
 */

dataset('staff tool gates', ['viewHorizon', 'viewPulse', 'viewSpectra']);

it('refuses an admin signed in on the web guard only', function (string $gate): void {
    $this->actingAs(adminUser(), 'web');

    expect(Gate::allows($gate))->toBeFalse();
})->with('staff tool gates');

it('allows an admin signed in to the admin panel, with or without two-factor', function (string $gate): void {
    Auth::guard('admin')->login(adminUser());
    expect(Gate::allows($gate))->toBeTrue();

    $withTwoFactor = adminUser();
    $withTwoFactor->saveAppAuthenticationSecret('JBSWY3DPEHPK3PXP');
    Auth::guard('admin')->login($withTwoFactor);
    expect(Gate::allows($gate))->toBeTrue();
})->with('staff tool gates');

it('refuses a deactivated admin and a non-admin on the panel guard', function (): void {
    $deactivated = adminUser();
    $deactivated->update(['deactivated_at' => now()]);
    Auth::guard('admin')->login($deactivated);
    expect(app(StaffToolsAccess::class)->allows())->toBeFalse();

    Auth::guard('admin')->login(User::factory()->workspace()->create());
    expect(app(StaffToolsAccess::class)->allows())->toBeFalse();
});
