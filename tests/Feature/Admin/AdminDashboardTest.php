<?php

use App\Admin\Pages\Dashboard;
use App\Admin\Pages\DashboardFilters;
use App\Admin\Widgets\ChannelsChart;
use App\Admin\Widgets\GrowthChart;
use App\Admin\Widgets\MessagesChart;
use App\Admin\Widgets\PlatformStatsOverview;
use App\Admin\Widgets\TopAgentsTable;
use App\Admin\Widgets\TopWorkspacesTable;
use App\Identity\Workspace\Models\User;
use Domain\Agent\Models\Agent;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

/**
 * The admin panel's dashboard: platform-wide stats, charts and the busiest
 * workspaces and agents, filtered by period and optionally one workspace.
 * The welcome card with its sign-out button is gone (sign-out lives in the
 * user menu).
 */
function adRollup(Agent $agent, string $bucketHour, array $values = []): void
{
    DB::connection(config('chatvia.analytics.connection'))
        ->table('analytics_agent_hourly_rollups')
        ->insert(array_merge([
            'agent_id' => $agent->id,
            'workspace_id' => $agent->workspace_id,
            'bucket_hour' => $bucketHour,
            'country_code' => '--',
            'llm_external_name' => '',
        ], $values));
}

beforeEach(function (): void {
    // A future month no seeded row falls in.
    $this->travelTo(Carbon::parse('2031-03-20 15:30:00', 'UTC'));
    $this->admin = User::factory()->admin()->create()->fresh();
    $this->acme = User::factory()->workspace()->create(['name' => 'Acme Corp', 'email' => 'owner@acme.test']);
    $this->bot = createAgent($this->acme, ['name' => 'Acme Helper']);

    adRollup($this->bot, '2031-03-10 10:00:00', ['user_messages' => 600, 'assistant_messages' => 634, 'conversations_started' => 77]);
});

it('opens on the dashboard without the welcome card', function (): void {
    $this->actingAs($this->admin, 'admin')
        ->get('/admin')
        ->assertOk()
        ->assertSee('Activity across every workspace, this month.')
        ->assertDontSee('fi-account-widget', escape: false);
});

it('shows every widget for the whole platform', function (): void {
    $widgets = Livewire::actingAs($this->admin, 'admin')
        ->test(Dashboard::class)
        ->instance()
        ->getWidgets();

    expect($widgets)->toBe([
        PlatformStatsOverview::class,
        MessagesChart::class,
        ChannelsChart::class,
        GrowthChart::class,
        TopWorkspacesTable::class,
        TopAgentsTable::class,
    ]);
});

it('leaves out the workspace-only widgets when one workspace is chosen', function (): void {
    $page = Livewire::actingAs($this->admin, 'admin')
        ->test(Dashboard::class)
        ->set('filters.'.DashboardFilters::WORKSPACE, $this->acme->workspace()->id);

    $page->assertSee('Activity in Acme Corp, this month.');

    expect($page->instance()->getWidgets())->not->toContain(GrowthChart::class, TopWorkspacesTable::class);
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

it('reads a custom date range, either way round', function (): void {
    $scope = DashboardFilters::scope([
        DashboardFilters::PERIOD => DashboardFilters::CUSTOM,
        DashboardFilters::START_DATE => '2031-03-12',
        DashboardFilters::END_DATE => '2031-03-05',
    ]);

    expect($scope->period->start->toDateTimeString())->toBe('2031-03-05 00:00:00')
        ->and($scope->period->end->toDateTimeString())->toBe('2031-03-12 23:59:59');

    // Until both dates are picked, a custom range shows this month.
    $incomplete = DashboardFilters::scope([DashboardFilters::PERIOD => DashboardFilters::CUSTOM, DashboardFilters::START_DATE => '2031-03-12']);

    expect($incomplete->period->start->toDateTimeString())->toBe('2031-03-01 00:00:00');
});

it('lists the busiest workspaces and agents', function (): void {
    Livewire::actingAs($this->admin, 'admin')
        ->test(TopWorkspacesTable::class, ['pageFilters' => []])
        ->assertSee('Acme Corp')
        ->assertSee('owner@acme.test')
        ->assertSee('1,234');

    Livewire::actingAs($this->admin, 'admin')
        ->test(TopAgentsTable::class, ['pageFilters' => []])
        ->assertSee('Acme Helper')
        ->assertSee('Acme Corp');
});

it('renders the charts', function (string $widget): void {
    Livewire::actingAs($this->admin, 'admin')
        ->test($widget, ['pageFilters' => [DashboardFilters::PERIOD => 'last_7_days']])
        ->assertOk();
})->with([
    'messages' => MessagesChart::class,
    'channels' => ChannelsChart::class,
    'growth' => GrowthChart::class,
]);
