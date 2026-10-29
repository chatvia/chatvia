<?php

declare(strict_types=1);

use Domain\Integration\Function\Actions\UpdateAgentFunctionAction;
use Domain\Integration\Function\Data\FunctionDefinitionData;
use Domain\Integration\Function\Enums\FunctionAuthType;
use Domain\Integration\Function\Enums\FunctionWebhookMethod;
use Domain\Integration\Function\Models\AgentFunction;
use Spatie\LaravelData\Optional;
use Support\Exceptions\DomainValidationException;

/**
 * UpdateAgentFunctionAction patches a webhook function. Its rules guard
 * security:
 *  - An update that omits `requires_approval` keeps the stored value, so an
 *    API consumer's partial PATCH cannot clear the approval gate the user set
 *    in the dashboard; a new verb only ever tightens it.
 *  - When `auth_type` changes (e.g. bearer → hmac) the previous type's secret
 *    is dropped and the new one comes from the submitted value.
 *  - A rename may not take another function's name in the workspace.
 */
function uafFunction(): AgentFunction
{
    return AgentFunction::factory()->requiringApproval()->create([
        'internal_name' => 'write_thing',
        'display_name' => 'Write Thing',
        'description' => 'Writes a thing',
        'webhook_url' => 'https://hooks.example.test/fn',
        'auth_type' => FunctionAuthType::Bearer,
        'auth_config' => ['token' => 'stored-secret-xxx'],
    ]);
}

/**
 * The whole definition the dashboard sends for uafFunction(), with $changes.
 *
 * @param  array<string, mixed>  $changes
 */
function uafData(array $changes = []): FunctionDefinitionData
{
    return new FunctionDefinitionData(...[
        'internal_name' => 'write_thing',
        'display_name' => 'Write Thing',
        'description' => 'Writes a thing',
        'parameters_schema' => ['type' => 'object', 'properties' => []],
        'webhook_url' => 'https://hooks.example.test/fn',
        'webhook_method' => FunctionWebhookMethod::Post,
        'auth_type' => FunctionAuthType::Bearer,
        'auth_header_name' => null,
        'auth_value' => null,
        'requires_approval' => new Optional,
        ...$changes,
    ]);
}

it('keeps requires_approval unless the caller sends it, and a new verb only tightens it', function (): void {
    $function = uafFunction();
    $update = fn (FunctionDefinitionData $data): AgentFunction => app(UpdateAgentFunctionAction::class)->execute($function, $data);

    $leftOut = $update(uafData())->fresh()->requires_approval;
    $toGet = $update(FunctionDefinitionData::from(['webhook_method' => 'GET']))->fresh()->requires_approval;
    $cleared = $update(uafData(['webhook_method' => FunctionWebhookMethod::Get, 'requires_approval' => false]))->fresh()->requires_approval;
    $toPatch = $update(FunctionDefinitionData::from(['webhook_method' => 'PATCH']))->fresh()->requires_approval;

    expect($leftOut)->toBeTrue()
        ->and($toGet)->toBeTrue()
        ->and($cleared)->toBeFalse()
        ->and($toPatch)->toBeTrue();
});

it('drops the stored secret when auth_type changes, keeping only a new value', function (?string $sent, array $config): void {
    $function = uafFunction();

    app(UpdateAgentFunctionAction::class)->execute($function, uafData(['auth_type' => FunctionAuthType::Hmac, 'auth_value' => $sent]));

    expect($function->fresh()->auth_type)->toBe(FunctionAuthType::Hmac)
        ->and($function->fresh()->auth_config)->toBe($config);
})->with([
    'no new value: an empty secret' => [null, ['secret' => '']],
    'a new value' => ['new-hmac-secret', ['secret' => 'new-hmac-secret']],
]);

it('refuses a rename to another function\'s name, but not to its own', function (): void {
    $function = uafFunction();
    AgentFunction::factory()->create(['workspace_id' => $function->workspace_id, 'internal_name' => 'taken']);

    expect(fn (): AgentFunction => app(UpdateAgentFunctionAction::class)->execute($function, FunctionDefinitionData::from(['internal_name' => 'taken'])))
        ->toThrow(DomainValidationException::class);

    expect($function->fresh()->internal_name)->toBe('write_thing')
        ->and(app(UpdateAgentFunctionAction::class)->execute($function, FunctionDefinitionData::from(['internal_name' => 'write_thing']))->internal_name)->toBe('write_thing');
});
