<?php

use App\Identity\Workspace\Models\User;
use Domain\Analytics\Data\Platform\TopAgentData;
use Domain\Analytics\Queries\Platform\TopAgentsQuery;
use Illuminate\Support\Carbon;
use Tests\Support\Analytics\PlatformScopes;

/**
 * The admin dashboard's busiest agents in the window, with their workspace.
 * Runs in a future month no seeded row falls in.
 */
beforeEach(function (): void {
    $this->travelTo(Carbon::parse('2031-03-20 15:30:00', 'UTC'));
});

it('ranks the busiest agents of the workspace', function (): void {
    $acme = User::factory()->workspace()->create(['name' => 'Acme']);
    analyticsRollup(createAgent($acme, ['name' => 'Sales']), '2031-03-10 10:00:00', ['user_messages' => 10, 'assistant_messages' => 10]);
    analyticsRollup(createAgent($acme, ['name' => 'Support']), '2031-03-11 10:00:00', ['user_messages' => 1, 'assistant_messages' => 1]);
    analyticsRollup(createAgent(User::factory()->workspace()->create(['name' => 'Globex'])), '2031-03-12 10:00:00', ['user_messages' => 60, 'assistant_messages' => 60]);

    $agents = app(TopAgentsQuery::class)->get(PlatformScopes::of(workspaceId: $acme->workspace()->id));

    expect(collect($agents)->map(fn (TopAgentData $row): ?string => $row->name)->all())->toBe(['Sales', 'Support'])
        ->and($agents[0]->workspaceName)->toBe('Acme')
        ->and($agents[0]->messages)->toBe(20);
});
