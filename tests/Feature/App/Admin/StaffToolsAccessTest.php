<?php

use App\Identity\Workspace\Models\User;

/**
 * Horizon, Pulse and Spectra open only for staff signed in to the admin
 * panel (its own `admin` guard), as their gates decide
 * (Horizon/Pulse/SpectraServiceProvider ask StaffToolsAccess). A dashboard
 * session on the shared `web` guard is not enough, except for Spectra for
 * the owner of a workspace an admin granted Spectra access. Who counts as
 * staff is in tests/Unit/App/Admin/StaffToolsAccessTest.php.
 */
dataset('staff tool dashboards', ['Horizon' => '/horizon', 'Pulse' => '/pulse', 'Spectra' => '/spectra']);

it('opens for an admin signed in to the admin panel', function (string $url): void {
    $this->actingAs(adminUser(), 'admin')->get($url)->assertOk();
})->with('staff tool dashboards');

it('refuses a guest and an admin signed in on the dashboard guard only', function (string $url): void {
    $this->get($url)->assertForbidden();

    $this->actingAs(adminUser(), 'web')->get($url)->assertForbidden();
})->with('staff tool dashboards');

it('opens Spectra on a dashboard session for the owner of a workspace granted access, never for its teammates', function (): void {
    $owner = User::factory()->create();
    $teammate = teammateWithPermissions($owner, ['agent.chat.view']);

    $this->actingAs($owner, 'web')->get('/spectra')->assertForbidden();

    $owner->workspace()->update(['spectra_access' => true]);

    $this->actingAs($owner->fresh(), 'web')->get('/spectra')->assertOk();
    $this->actingAs($teammate->fresh(), 'web')->get('/spectra')->assertForbidden();
});
