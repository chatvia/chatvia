<?php

use App\Admin\Widgets\TopAgentsTable;
use App\Identity\Workspace\Models\User;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

/**
 * The busiest agents of the admin dashboard, with their workspace. The
 * ranking is TopAgentsQueryTest's.
 */
it('lists the busiest agents with their workspace', function (): void {
    // A future month no seeded row falls in.
    $this->travelTo(Carbon::parse('2031-03-20 15:30:00', 'UTC'));
    $acme = User::factory()->workspace()->create(['name' => 'Acme Corp']);
    analyticsRollup(createAgent($acme, ['name' => 'Acme Helper']), '2031-03-10 10:00:00', ['user_messages' => 600, 'assistant_messages' => 634]);

    Livewire::actingAs(adminUser(), 'admin')
        ->test(TopAgentsTable::class, ['pageFilters' => []])
        ->assertSee('Acme Helper')
        ->assertSee('Acme Corp');
});
