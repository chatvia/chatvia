<?php

use App\Identity\Workspace\Models\User;

/**
 * Horizon, Pulse and Spectra open only for staff signed in to the admin
 * panel (its own `admin` guard), as their gates decide
 * (Horizon/Pulse/SpectraServiceProvider ask StaffToolsAccess). A dashboard
 * session on the shared `web` guard is not enough, except for Spectra for
 * the owner of a workspace an admin granted Spectra access. Who counts as staff is in
 * tests/Unit/App/Admin/StaffToolsAccessTest.php.
 */
dataset('staff tool dashboards', ['Horizon' => ['/horizon', 'Horizon'], 'Pulse' => ['/pulse', 'Pulse'], 'Spectra' => ['/spectra', 'Spectra']]);

it('opens for an admin signed in to the admin panel', function (string $url, string $tool): void {
    $this->actingAs(adminUser(), 'admin')->get($url)->assertOk()->assertSee($tool);
})->with('staff tool dashboards');

it('refuses an admin signed in on the dashboard guard only', function (string $url): void {
    $this->actingAs(adminUser(), 'web')->get($url)->assertForbidden();
})->with('staff tool dashboards');

it('refuses a guest', function (string $url): void {
    $this->get($url)->assertForbidden();
})->with('staff tool dashboards');

it('opens Spectra on a dashboard session once its workspace was granted Spectra access', function (): void {
    $owner = User::factory()->create();

    $this->actingAs($owner, 'web')->get('/spectra')->assertForbidden();

    $owner->workspace()->update(['spectra_access' => true]);

    $this->actingAs($owner->fresh(), 'web')->get('/spectra')->assertOk();
});

it('keeps Spectra closed to the teammates of a workspace granted Spectra access', function (): void {
    $owner = User::factory()->create();
    $owner->workspace()->update(['spectra_access' => true]);
    $teammate = teammateWithPermissions($owner, ['agent.chat.view']);

    $this->actingAs($teammate, 'web')->get('/spectra')->assertForbidden();
});
