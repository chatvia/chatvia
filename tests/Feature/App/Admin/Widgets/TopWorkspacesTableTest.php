<?php

use App\Admin\Widgets\TopWorkspacesTable;
use App\Identity\Workspace\Models\User;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

/**
 * The busiest workspaces of the admin dashboard, with their owner and their
 * messages in the period. The ranking is TopWorkspacesQueryTest's.
 */
it('lists the busiest workspaces with their owner and messages', function (): void {
    // A future month no seeded row falls in.
    $this->travelTo(Carbon::parse('2031-03-20 15:30:00', 'UTC'));
    $acme = User::factory()->workspace()->create(['name' => 'Acme Corp', 'email' => 'owner@acme.test']);
    analyticsRollup(createAgent($acme), '2031-03-10 10:00:00', ['user_messages' => 600, 'assistant_messages' => 634]);

    Livewire::actingAs(adminUser(), 'admin')
        ->test(TopWorkspacesTable::class, ['pageFilters' => []])
        ->assertSee('Acme Corp')
        ->assertSee('owner@acme.test')
        ->assertSee('1,234');
});
