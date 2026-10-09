<?php

use App\Identity\Workspace\Models\User;
use Domain\Agent\Models\Agent;
use Domain\Integration\Function\Actions\ListAgentFunctionsAction;
use Domain\Integration\Function\Data\Resources\AgentFunctionRowData;
use Domain\Integration\Function\Models\AgentFunction;

/**
 * The agent Functions page lists every function of the workspace that owns the
 * agent, sorted by name, and marks the ones this agent uses. Functions of other
 * workspaces never appear.
 */
function listedFunction(int $ownerId, string $name): AgentFunction
{
    return AgentFunction::query()->create([
        'user_id' => $ownerId,
        'internal_name' => $name,
        'description' => 'Function '.$name,
        'parameters_schema' => ['type' => 'object', 'properties' => []],
        'webhook_url' => 'https://example.com/'.$name,
        'webhook_method' => 'POST',
    ]);
}

it('lists the workspace functions by name and marks the attached ones', function (): void {
    $agent = Agent::factory()->create();
    $refund = listedFunction($agent->user_id, 'refund_order');
    $lookup = listedFunction($agent->user_id, 'lookup_order');
    listedFunction(User::factory()->create()->id, 'foreign_function');
    $agent->functions()->attach($refund->id);

    $rows = app(ListAgentFunctionsAction::class)->execute($agent)->data->toCollection();

    expect($rows->map(fn (AgentFunctionRowData $row): array => [$row->external_id, $row->is_attached])->all())
        ->toBe([
            [$lookup->external_id, false],
            [$refund->external_id, true],
        ]);
});

it('returns an empty list for a workspace without functions', function (): void {
    expect(app(ListAgentFunctionsAction::class)->execute(Agent::factory()->create())->data->toCollection())->toBeEmpty();
});
