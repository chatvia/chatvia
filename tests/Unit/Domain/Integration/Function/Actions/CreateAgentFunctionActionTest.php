<?php

declare(strict_types=1);

use App\Identity\Workspace\Models\User;
use Domain\Integration\Function\Actions\CreateAgentFunctionAction;
use Domain\Integration\Function\Data\FunctionDefinitionData;
use Domain\Integration\Function\Models\AgentFunction;
use Support\Exceptions\DomainValidationException;

/**
 * Creating a webhook function, shared by the dashboard and the API: the row
 * is keyed to the workspace whoever creates it, approval follows the verb,
 * and the name is unique per workspace.
 */
function cafData(string $name, string $method = 'POST'): FunctionDefinitionData
{
    return FunctionDefinitionData::from([
        'internal_name' => $name,
        'description' => 'Look up an order',
        'parameters_schema' => ['type' => 'object', 'properties' => []],
        'webhook_url' => 'https://example.com/webhook',
        'webhook_method' => $method,
    ]);
}

it('keys a function created by a teammate to the workspace, with approval from the verb', function (): void {
    $owner = User::factory()->create();
    $member = User::factory()->employee($owner)->create();

    $function = app(CreateAgentFunctionAction::class)->execute($member, cafData('lookup_order', 'GET'));

    expect($function->workspace_id)->toBe($owner->workspace()->id)
        ->and($function->requires_approval)->toBeFalse();
});

it('refuses a name the workspace already uses, and stores nothing', function (): void {
    $owner = User::factory()->create();
    app(CreateAgentFunctionAction::class)->execute($owner, cafData('taken'));

    expect(fn (): AgentFunction => app(CreateAgentFunctionAction::class)->execute($owner, cafData('taken')))
        ->toThrow(DomainValidationException::class);

    expect(AgentFunction::query()->where('workspace_id', $owner->workspace()->id)->count())->toBe(1);
});
