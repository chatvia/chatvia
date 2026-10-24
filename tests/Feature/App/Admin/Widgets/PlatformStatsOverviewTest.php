<?php

use App\Admin\Pages\DashboardFilters;
use App\Admin\Widgets\PlatformStatsOverview;
use App\Identity\Workspace\Models\User;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

/**
 * The headline numbers of the admin dashboard for the chosen period, across
 * every workspace or for one, which leaves out the workspace counts. The
 * numbers themselves are PlatformOverviewQueryTest's.
 */
beforeEach(function (): void {
    // A future month no seeded row falls in.
    $this->travelTo(Carbon::parse('2031-03-20 15:30:00', 'UTC'));
    $this->admin = adminUser();
    $this->acme = User::factory()->workspace()->create(['name' => 'Acme Corp']);

    analyticsRollup(createAgent($this->acme), '2031-03-10 10:00:00', ['user_messages' => 600, 'assistant_messages' => 634, 'conversations_started' => 77]);
});

it('shows the headline numbers for the chosen period', function (): void {
    Livewire::actingAs($this->admin, 'admin')
        ->test(PlatformStatsOverview::class, ['pageFilters' => [DashboardFilters::PERIOD => 'this_month']])
        ->assertSee('Messages')
        ->assertSee('1,234')
        ->assertSee('77')
        ->assertSee('Active workspaces');

    // Last month had no traffic.
    Livewire::actingAs($this->admin, 'admin')
        ->test(PlatformStatsOverview::class, ['pageFilters' => [DashboardFilters::PERIOD => 'last_month']])
        ->assertDontSee('1,234');
});

it('drops the workspace counts from the stats for one workspace', function (): void {
    Livewire::actingAs($this->admin, 'admin')
        ->test(PlatformStatsOverview::class, ['pageFilters' => [DashboardFilters::WORKSPACE => $this->acme->workspace()->id]])
        ->assertSee('1,234')
        ->assertDontSee('Active workspaces')
        ->assertDontSee('New workspaces');
});
