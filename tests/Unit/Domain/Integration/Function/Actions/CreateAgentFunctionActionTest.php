<?php

declare(strict_types=1);

use App\Identity\Workspace\Models\User;
use Domain\Integration\Function\Actions\CreateAgentFunctionAction;
use Domain\Integration\Function\Data\FunctionDefinitionData;

/**
 * Creating a webhook function, shared by the dashboard and the API: the row
 * is keyed to the workspace whoever creates it, and approval follows the
 * verb. The feature tests cover the unique name.
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
