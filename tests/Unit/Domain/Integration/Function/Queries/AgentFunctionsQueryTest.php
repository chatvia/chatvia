<?php

use App\Identity\Workspace\Models\Workspace;
use Domain\Agent\Models\Agent;
use Domain\Integration\Function\Data\Resources\AgentFunctionRowData;
use Domain\Integration\Function\Models\AgentFunction;
use Domain\Integration\Function\Queries\AgentFunctionsQuery;

/**
 * The agent Functions page lists every function of the workspace that owns the
 * agent, sorted by name, and marks the ones this agent uses. Functions of other
 * workspaces never appear.
 */
it('lists the workspace functions by name and marks the attached ones', function (): void {
    $agent = Agent::factory()->create();
    $refund = AgentFunction::factory()->attachedTo($agent)->create(['internal_name' => 'refund_order']);
    $lookup = AgentFunction::factory()->create(['workspace_id' => $agent->workspace_id, 'internal_name' => 'lookup_order']);
    AgentFunction::factory()->create(['workspace_id' => Workspace::factory()->create()->id, 'internal_name' => 'foreign_function']);

    $rows = app(AgentFunctionsQuery::class)->get($agent)->data->toCollection();

    expect($rows->map(fn (AgentFunctionRowData $row): array => [$row->external_id, $row->is_attached])->all())
        ->toBe([
            [$lookup->external_id, false],
            [$refund->external_id, true],
        ]);
});

it('returns an empty list for a workspace without functions', function (): void {
    expect(app(AgentFunctionsQuery::class)->get(Agent::factory()->create())->data->toCollection())->toBeEmpty();
});
