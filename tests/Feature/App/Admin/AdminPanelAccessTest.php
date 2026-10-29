<?php

use App\Identity\Workspace\Models\User;
use Filament\Facades\Filament;

/**
 * The Filament admin panel signs in on its own `admin` guard. A session opened
 * by the dashboard login (/login) or the hosted-chat login lives on the `web`
 * guard and must never open /admin, so the panel's own login (and its optional
 * authenticator-app MFA) is always used. Who may use the panel at all is
 * User::canAccessPanel() (UserTest).
 */
it('does not let a dashboard (web guard) session into the panel', function (): void {
    $this->actingAs(adminUser(), 'web')
        ->get('/admin')
        ->assertRedirect(Filament::getPanel('admin')->getLoginUrl());
});

it('opens the panel for an admin on the panel guard, with or without MFA, because MFA is optional', function (bool $mfa): void {
    $admin = adminUser();

    if ($mfa) {
        $admin->saveAppAuthenticationSecret('JBSWY3DPEHPK3PXP');
    }

    $this->actingAs($admin, 'admin')
        ->get('/admin')
        ->assertOk();
})->with(['without MFA' => false, 'with MFA set up' => true]);

it('keeps out of the panel a deactivated admin and an account that is not an admin, even on the panel guard', function (User $user): void {
    $this->actingAs($user, 'admin')
        ->get('/admin')
        ->assertForbidden();
})->with([
    'a deactivated admin with MFA' => fn (): User => tap(adminUser(), function (User $admin): void {
        $admin->saveAppAuthenticationSecret('JBSWY3DPEHPK3PXP');
        $admin->forceFill(['deactivated_at' => now()])->save();
    }),
    'not an admin' => fn (): User => User::factory()->create()->fresh(),
]);
