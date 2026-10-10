<?php

use App\Identity\Workspace\Models\User;
use Filament\Facades\Filament;

/**
 * The Filament admin panel signs in on its own `admin` guard. A session opened
 * by the dashboard login (/login) or the hosted-chat login lives on the `web`
 * guard and must never open /admin, so the panel's own login (and its optional
 * authenticator-app MFA) is always used.
 *
 * MFA is required only in production (AdminPanelProvider
 * `isRequired: App::isProduction()`); these tests run outside production, where
 * it is offered but optional. Staff tools (Horizon, Spectra)
 * always require MFA — see StaffToolsAccess.
 */
beforeEach(function (): void {
    // fresh(): load every column, as a real request does (strict mode flags
    // attributes a factory instance never had).
    $this->admin = User::factory()->admin()->create()->fresh();
});

it('does not let a dashboard (web guard) session into the panel', function (): void {
    $this->actingAs($this->admin, 'web')
        ->get('/admin')
        ->assertRedirect(Filament::getPanel('admin')->getLoginUrl());
});

it('opens the panel for an admin without MFA, because MFA is optional', function (): void {
    $this->actingAs($this->admin, 'admin')
        ->get('/admin')
        ->assertOk();
});

it('opens the panel for an admin signed in on the panel guard with MFA set up', function (): void {
    $this->admin->saveAppAuthenticationSecret('JBSWY3DPEHPK3PXP');

    $this->actingAs($this->admin, 'admin')
        ->get('/admin')
        ->assertOk();
});

it('keeps a deactivated admin out of the panel', function (): void {
    $this->admin->saveAppAuthenticationSecret('JBSWY3DPEHPK3PXP');
    $this->admin->forceFill(['deactivated_at' => now()])->save();

    $this->actingAs($this->admin, 'admin')
        ->get('/admin')
        ->assertForbidden();
});
