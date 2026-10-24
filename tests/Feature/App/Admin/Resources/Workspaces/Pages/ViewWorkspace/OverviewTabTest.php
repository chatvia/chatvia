<?php

use App\Admin\Pages\Dashboard;
use App\Admin\Resources\Workspaces\Pages\ViewWorkspace;
use App\Admin\Resources\Workspaces\WorkspaceResource;
use App\Identity\Chatter\Models\Chatter;
use App\Identity\Workspace\Models\User;
use Livewire\Livewire;
use Support\Localization\Country\Models\Country;

/**
 * The admin panel's page for one workspace opens on its Overview: the
 * owner's account, with the Analytics, Agents and Teammates tabs next to
 * it, and a link to the dashboard filtered to the workspace.
 */
beforeEach(function (): void {
    $this->admin = adminUser();
    $this->owner = User::factory()->create([
        'name' => 'Dana Owner',
        'email' => 'dana@acme.test',
        'locale' => 'de',
        'registered_country_code' => 'de',
        'registered_ip' => '203.0.113.9',
    ]);
    $this->workspace = $this->owner->workspace();
    $this->workspace->update(['name' => 'Acme Corp']);
});

it('shows the owner\'s account details on the overview', function (): void {
    Country::firstOrCreate(['code' => 'de'], ['name' => 'Germany']);
    Chatter::factory()->ofWorkspace($this->owner)->count(2)->create();

    $this->actingAs($this->admin, 'admin')
        ->get(WorkspaceResource::getUrl('view', ['record' => $this->workspace]))
        ->assertOk()
        ->assertSee('Acme Corp')
        ->assertSee('dana@acme.test')
        ->assertSee('German (Deutsch)')
        ->assertSee('Germany (DE)')
        ->assertSee('203.0.113.9')
        ->assertSee('Active')
        ->assertSeeInOrder(['Overview', 'Analytics', 'Agents', 'Teammates']);
});

it('opens on the Overview tab and links to the dashboard filtered to the workspace', function (): void {
    Livewire::actingAs($this->admin, 'admin')
        ->test(ViewWorkspace::class, ['record' => $this->workspace->getRouteKey()])
        ->assertSet('activeRelationManager', null)
        ->assertActionHasUrl('openDashboard', Dashboard::getUrl(['filters' => ['workspace' => $this->workspace->id]]));
});
