<?php

use App\Identity\Workspace\Models\User;
use Domain\Integration\Function\Actions\CreateAgentFunctionAction;
use Domain\Integration\Function\Actions\UpdateAgentFunctionAction;
use Domain\Integration\Function\Data\FunctionDefinitionData;
use Domain\Integration\Function\Models\AgentFunction;
use Support\Exceptions\DomainValidationException;

/**
 * The create/update Actions shared by the dashboard and the API: names
 * unique per workspace, approval derived from the verb, rows keyed to the
 * workspace, and a partial update only changes what it was given.
 */
function apiFunctionPayload(array $fields): FunctionDefinitionData
{
    return FunctionDefinitionData::from($fields);
}

it('keys a function created for a teammate to the workspace, with approval from the verb', function (): void {
    $owner = User::factory()->create();
    $member = User::factory()->employee($owner)->create();

    $function = app(CreateAgentFunctionAction::class)->execute($member, apiFunctionPayload([
        'internal_name' => 'lookup_order',
        'description' => 'Look up an order',
        'parameters_schema' => ['type' => 'object', 'properties' => []],
        'webhook_url' => 'https://example.com/webhook',
        'webhook_method' => 'GET',
    ]));

    expect($function->workspace_id)->toBe($owner->workspace()->id)
        ->and($function->requires_approval)->toBeFalse();
});

it('rejects a duplicate name on create and on rename', function (): void {
    $owner = User::factory()->create();
    $base = [
        'description' => 'd',
        'parameters_schema' => ['type' => 'object', 'properties' => []],
        'webhook_url' => 'https://example.com/webhook',
    ];

    app(CreateAgentFunctionAction::class)->execute($owner, apiFunctionPayload(['internal_name' => 'taken'] + $base));
    $other = app(CreateAgentFunctionAction::class)->execute($owner, apiFunctionPayload(['internal_name' => 'other'] + $base));

    expect(fn (): AgentFunction => app(CreateAgentFunctionAction::class)->execute($owner, apiFunctionPayload(['internal_name' => 'taken'] + $base)))
        ->toThrow(DomainValidationException::class);

    expect(fn (): AgentFunction => app(UpdateAgentFunctionAction::class)->execute($other, apiFunctionPayload(['internal_name' => 'taken'])))
        ->toThrow(DomainValidationException::class);

    // Renaming to its own current name is not a clash.
    expect(app(UpdateAgentFunctionAction::class)->execute($other, apiFunctionPayload(['internal_name' => 'other']))->internal_name)
        ->toBe('other');
});

it('only tightens approval when the verb changes', function (): void {
    $function = AgentFunction::factory()->requiringApproval()->create();

    // POST → GET keeps the gate the user had.
    expect(app(UpdateAgentFunctionAction::class)->execute($function, apiFunctionPayload(['webhook_method' => 'GET']))->requires_approval)
        ->toBeTrue();

    $function->forceFill(['requires_approval' => false])->save();

    // GET → PATCH turns it on.
    expect(app(UpdateAgentFunctionAction::class)->execute($function, apiFunctionPayload(['webhook_method' => 'PATCH']))->requires_approval)
        ->toBeTrue();
});
