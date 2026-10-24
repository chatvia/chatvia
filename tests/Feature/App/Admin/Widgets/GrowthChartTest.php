<?php

use App\Admin\Pages\DashboardFilters;
use App\Admin\Widgets\GrowthChart;
use App\Identity\Workspace\Models\User;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\Support\Admin\ChartData;

/**
 * The Growth chart of the admin dashboard: the new workspaces and agents of
 * each day of the period, as the browser gets them.
 */
it('charts the new workspaces and agents of each day', function (): void {
    // A future month no seeded row falls in.
    $this->travelTo(Carbon::parse('2031-03-20 15:30:00', 'UTC'));
    $admin = adminUser();
    createAgent(User::factory()->workspace()->create());

    $data = ChartData::of(Livewire::actingAs($admin, 'admin')
        ->test(GrowthChart::class, ['pageFilters' => [DashboardFilters::PERIOD => 'this_month']]));

    // Today: the admin's workspace and the new one, and its agent.
    expect($data['datasets'][0]['label'])->toBe('New workspaces')
        ->and($data['datasets'][0]['data'])->toBe([...array_fill(0, 19, 0), 2])
        ->and($data['datasets'][1]['label'])->toBe('New agents')
        ->and($data['datasets'][1]['data'])->toBe([...array_fill(0, 19, 0), 1]);
});
