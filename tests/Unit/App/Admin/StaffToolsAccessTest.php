<?php

use App\Admin\StaffToolsAccess;
use App\Identity\Workspace\Models\User;
use Illuminate\Support\Facades\Auth;

/**
 * StaffToolsAccess: the staff tools open for an active admin signed in to
 * the admin panel's own guard, with or without two-factor sign-in (it is
 * optional, as for the panel). The routes that ask it are in
 * tests/Feature/App/Admin/StaffToolsAccessTest.php.
 */
it('lets in an admin of the panel guard, with or without two-factor', function (bool $twoFactor): void {
    $admin = adminUser();

    if ($twoFactor) {
        $admin->saveAppAuthenticationSecret('JBSWY3DPEHPK3PXP');
    }

    Auth::guard('admin')->login($admin);

    expect(app(StaffToolsAccess::class)->allows())->toBeTrue();
})->with(['without two-factor' => false, 'with two-factor' => true]);

it('keeps out a deactivated admin and an account that is not an admin, even on the panel guard', function (User $user): void {
    Auth::guard('admin')->login($user);

    expect(app(StaffToolsAccess::class)->allows())->toBeFalse();
})->with([
    'a deactivated admin' => fn (): User => tap(adminUser())->update(['deactivated_at' => now()]),
    'not an admin' => fn (): User => User::factory()->workspace()->create(),
]);

it('keeps out an admin signed in on the dashboard guard only', function (): void {
    Auth::guard('web')->login(adminUser());

    expect(app(StaffToolsAccess::class)->allows())->toBeFalse();
});
