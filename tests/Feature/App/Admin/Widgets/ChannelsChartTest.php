<?php

use App\Admin\Pages\DashboardFilters;
use App\Admin\Widgets\ChannelsChart;
use Domain\Agent\Models\Agent;
use Domain\Conversation\Enums\ConversationSource;
use Domain\Conversation\Models\Conversation;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\Support\Admin\ChartData;

/**
 * The Channels chart of the admin dashboard: the conversations of the
 * period by channel, busiest first, or its empty state when there were
 * none. The counting is PlatformChannelQueryTest's.
 */
beforeEach(function (): void {
    // A future month no seeded row falls in.
    $this->travelTo(Carbon::parse('2031-03-20 15:30:00', 'UTC'));
    $this->admin = adminUser();
});

it('charts the conversations of the period by channel, busiest first', function (): void {
    $agent = Agent::factory()->create();

    foreach ([ConversationSource::Widget, ConversationSource::Widget, ConversationSource::Telegram] as $source) {
        Conversation::factory()->create(['agent_id' => $agent->id, 'source' => $source, 'is_visible' => true]);
    }

    $data = ChartData::of(Livewire::actingAs($this->admin, 'admin')
        ->test(ChannelsChart::class, ['pageFilters' => [DashboardFilters::PERIOD => 'this_month']]));

    expect($data['labels'])->toBe(['Chat Widget', 'Telegram'])
        ->and($data['datasets'][0]['data'])->toBe([2, 1]);
});

it('shows its empty state for a period without conversations', function (): void {
    $chart = Livewire::actingAs($this->admin, 'admin')
        ->test(ChannelsChart::class, ['pageFilters' => [DashboardFilters::PERIOD => 'last_month']]);

    expect($chart->instance()->isEmpty())->toBeTrue();
});
