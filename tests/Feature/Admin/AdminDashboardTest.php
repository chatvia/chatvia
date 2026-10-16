<?php

use App\Admin\Pages\Dashboard;
use App\Admin\Pages\DashboardFilters;
use App\Admin\Widgets\ChannelsChart;
use App\Admin\Widgets\GrowthChart;
use App\Admin\Widgets\MessagesChart;
use App\Admin\Widgets\PlatformStatsOverview;
use App\Admin\Widgets\ServiceStatusBanner;
use App\Admin\Widgets\TopAgentsTable;
use App\Admin\Widgets\TopWorkspacesTable;
use App\Identity\Workspace\Models\User;
use Domain\Conversation\Enums\ConversationSource;
use Domain\Conversation\Models\Conversation;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

use function Livewire\invade;

/**
 * The admin panel's dashboard: platform-wide stats, charts and the busiest
 * workspaces and agents, filtered by period and optionally one workspace.
 * It has no welcome card: signing out is in the user menu.
 */
beforeEach(function (): void {
    // A future month no seeded row falls in.
    $this->travelTo(Carbon::parse('2031-03-20 15:30:00', 'UTC'));
    $this->admin = User::factory()->admin()->create()->fresh();
    $this->acme = User::factory()->workspace()->create(['name' => 'Acme Corp', 'email' => 'owner@acme.test']);
    $this->bot = createAgent($this->acme, ['name' => 'Acme Helper']);

    analyticsRollup($this->bot, '2031-03-10 10:00:00', ['user_messages' => 600, 'assistant_messages' => 634, 'conversations_started' => 77]);
});

it('opens on the dashboard, with no welcome card', function (): void {
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
        ServiceStatusBanner::class,
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
        ->set('filters', [DashboardFilters::WORKSPACE => $this->acme->workspace()->id]);

    $page->assertSee('Activity in Acme Corp, this month.');

    expect($page->instance()->getWidgets())->not->toContain(GrowthChart::class, TopWorkspacesTable::class);
});

it('applies the filters from the filter button', function (): void {
    $workspaceId = $this->acme->workspace()->id;

    Livewire::actingAs($this->admin, 'admin')
        ->test(Dashboard::class)
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

it('charts the AI replies and visitor messages of each day', function (): void {
    $data = invade(Livewire::actingAs($this->admin, 'admin')
        ->test(MessagesChart::class, ['pageFilters' => [DashboardFilters::PERIOD => 'this_month']])
        ->instance())->getData();

    // One bucket per day of March up to today (the 20th); the traffic is on the 10th.
    $replies = array_fill(0, 20, 0);
    $replies[9] = 634;
    $visitors = array_fill(0, 20, 0);
    $visitors[9] = 600;

    expect($data['labels'])->toHaveCount(20)
        ->and($data['labels'][9])->toBe('Mar 10')
        ->and($data['datasets'][0]['label'])->toBe('AI replies')
        ->and($data['datasets'][0]['data'])->toBe($replies)
        ->and($data['datasets'][1]['label'])->toBe('Visitor messages')
        ->and($data['datasets'][1]['data'])->toBe($visitors);
});

it('charts the conversations of the period by channel, busiest first', function (): void {
    foreach ([ConversationSource::Widget, ConversationSource::Widget, ConversationSource::Telegram] as $source) {
        Conversation::factory()->create(['agent_id' => $this->bot->id, 'source' => $source, 'is_visible' => true]);
    }

    $data = invade(Livewire::actingAs($this->admin, 'admin')
        ->test(ChannelsChart::class, ['pageFilters' => [DashboardFilters::PERIOD => 'this_month']])
        ->instance())->getData();

    expect($data['labels'])->toBe(['Chat Widget', 'Telegram'])
        ->and($data['datasets'][0]['data'])->toBe([2, 1]);

    // Last month had none, so the chart shows its empty state.
    $lastMonth = invade(Livewire::actingAs($this->admin, 'admin')
        ->test(ChannelsChart::class, ['pageFilters' => [DashboardFilters::PERIOD => 'last_month']])
        ->instance())->getData();

    expect($lastMonth)->toBe([]);
});

it('charts the new workspaces and agents of each day', function (): void {
    $data = invade(Livewire::actingAs($this->admin, 'admin')
        ->test(GrowthChart::class, ['pageFilters' => [DashboardFilters::PERIOD => 'this_month']])
        ->instance())->getData();

    // Today: the admin's and Acme's workspaces, and Acme's agent.
    expect($data['datasets'][0]['label'])->toBe('New workspaces')
        ->and($data['datasets'][0]['data'][19])->toBe(2)
        ->and(array_sum($data['datasets'][0]['data']))->toBe(2)
        ->and($data['datasets'][1]['label'])->toBe('New agents')
        ->and($data['datasets'][1]['data'][19])->toBe(1)
        ->and(array_sum($data['datasets'][1]['data']))->toBe(1);
});
