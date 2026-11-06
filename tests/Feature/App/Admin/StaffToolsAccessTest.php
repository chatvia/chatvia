<?php

use App\Identity\Workspace\Models\User;
use App\Identity\Workspace\Services\WorkspaceDashboards;

/**
 * Horizon, Pulse and Spectra open only for staff signed in to the admin
 * panel (its own `admin` guard), as their gates decide
 * (Horizon/Pulse/SpectraServiceProvider ask StaffToolsAccess). A dashboard
 * session on the shared `web` guard is not enough. Who counts as
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

it('never opens Spectra on a dashboard session, even for a workspace owner with a dashboard: it shows every workspace', function (): void {
    $owner = User::factory()->create();
    app(WorkspaceDashboards::class)->create($owner->workspace(), [], false);

    $this->actingAs($owner, 'web')->get('/spectra')->assertForbidden();
});
