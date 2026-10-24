<?php

use App\Admin\Pages\DashboardFilters;
use App\Admin\Widgets\MessagesChart;
use App\Identity\Workspace\Models\User;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\Support\Admin\ChartData;

/**
 * The Messages chart of the admin dashboard: the AI replies and the visitor
 * messages of each day of the period, as the browser gets them. The buckets
 * are PlatformActivityQueryTest's.
 */
it('charts the AI replies and visitor messages of each day', function (): void {
    // A future month no seeded row falls in.
    $this->travelTo(Carbon::parse('2031-03-20 15:30:00', 'UTC'));
    analyticsRollup(createAgent(User::factory()->workspace()->create()), '2031-03-10 10:00:00', ['user_messages' => 600, 'assistant_messages' => 634]);

    $data = ChartData::of(Livewire::actingAs(adminUser(), 'admin')
        ->test(MessagesChart::class, ['pageFilters' => [DashboardFilters::PERIOD => 'this_month']]));

    // One bucket per day of March up to today (the 20th); the traffic is on the 10th.
    expect($data['labels'])->toHaveCount(20)
        ->and($data['labels'][9])->toBe('Mar 10')
        ->and($data['datasets'][0]['label'])->toBe('AI replies')
        ->and($data['datasets'][0]['data'])->toBe([0, 0, 0, 0, 0, 0, 0, 0, 0, 634, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0])
        ->and($data['datasets'][1]['label'])->toBe('Visitor messages')
        ->and($data['datasets'][1]['data'])->toBe([0, 0, 0, 0, 0, 0, 0, 0, 0, 600, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0]);
});
