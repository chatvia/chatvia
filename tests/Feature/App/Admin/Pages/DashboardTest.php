<?php

use App\Admin\Pages\Dashboard;
use App\Admin\Pages\DashboardFilters;
use App\Admin\Widgets\GrowthChart;
use App\Admin\Widgets\TopWorkspacesTable;
use App\Identity\Workspace\Models\User;

/**
 * The admin panel's dashboard: platform-wide stats, charts and the busiest
 * workspaces and agents, filtered by period and optionally one workspace.
 * It has no welcome card: signing out is in the user menu. Each widget has
 * a test of its own in tests/Feature/App/Admin/Widgets.
 */
beforeEach(function (): void {
    $this->admin = adminUser();
    $this->acme = User::factory()->workspace()->create(['name' => 'Acme Corp']);
});

it('opens on the dashboard, with no welcome card', function (): void {
    $this->actingAs($this->admin, 'admin')
        ->get('/admin')
        ->assertOk()
        ->assertSee('Activity across every workspace, this month.')
        ->assertDontSee('fi-account-widget', escape: false);
});

it('leaves out the workspace-only widgets when one workspace is chosen', function (): void {
    $page = adminPage(Dashboard::class, $this->admin)
        ->set('filters', [DashboardFilters::WORKSPACE => $this->acme->workspace()->id]);

    $page->assertSee('Activity in Acme Corp, this month.');

    expect($page->instance()->getWidgets())->not->toContain(GrowthChart::class, TopWorkspacesTable::class);
});

it('applies the filters from the filter button', function (): void {
    $workspaceId = $this->acme->workspace()->id;

    adminPage(Dashboard::class, $this->admin)
        ->assertActionExists('filter')
        ->callAction('filter', data: [
            DashboardFilters::PERIOD => 'last_month',
            DashboardFilters::WORKSPACE => $workspaceId,
        ])
        ->assertHasNoFormErrors()
        ->assertSet('filters.'.DashboardFilters::PERIOD, 'last_month')
        ->assertSet('filters.'.DashboardFilters::WORKSPACE, $workspaceId)
        ->assertSee('Activity in Acme Corp, last month.');
});
