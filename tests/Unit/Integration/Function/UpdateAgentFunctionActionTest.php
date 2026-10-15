<?php

use Domain\Integration\Function\Actions\UpdateAgentFunctionAction;
use Domain\Integration\Function\Data\FunctionDefinitionData;
use Domain\Integration\Function\Enums\FunctionAuthType;
use Domain\Integration\Function\Enums\FunctionWebhookMethod;
use Domain\Integration\Function\Models\AgentFunction;
use Spatie\LaravelData\Optional;

/**
 * UpdateAgentFunctionAction patches a webhook function. Two of its rules guard
 * security:
 *  - An update that omits `requires_approval` keeps the stored value, so an
 *    API consumer's partial PATCH cannot clear the approval gate the user set
 *    in the dashboard.
 *  - When `auth_type` changes (e.g. bearer → hmac) the previous type's secret
 *    is dropped and the new one comes from the submitted value.
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

it('preserves requires_approval when the caller omits the field', function (): void {
    $function = uafFunction();

    $data = new FunctionDefinitionData(
        internal_name: 'write_thing',
        display_name: 'Write Thing',
        description: 'Writes a thing',
        parameters_schema: ['type' => 'object', 'properties' => []],
        webhook_url: 'https://hooks.example.test/fn',
        webhook_method: FunctionWebhookMethod::Post,
        auth_type: FunctionAuthType::Bearer,
        auth_header_name: null,
        auth_value: null,
        requires_approval: new Optional,
    );

    app(UpdateAgentFunctionAction::class)->execute($function, $data);

    $function->refresh();
    expect($function->requires_approval)->toBeTrue();
});

it('updates requires_approval when the caller explicitly sends false', function (): void {
    $function = uafFunction();

    $data = new FunctionDefinitionData(
        internal_name: 'write_thing',
        display_name: 'Write Thing',
        description: 'Writes a thing',
        parameters_schema: ['type' => 'object', 'properties' => []],
        webhook_url: 'https://hooks.example.test/fn',
        webhook_method: FunctionWebhookMethod::Post,
        auth_type: FunctionAuthType::Bearer,
        auth_header_name: null,
        auth_value: null,
        requires_approval: false,
    );

    app(UpdateAgentFunctionAction::class)->execute($function, $data);

    expect($function->refresh()->requires_approval)->toBeFalse();
});

it('CLEARS the stored secret when auth_type changes (bearer → hmac) without a fresh value', function (): void {
    $function = uafFunction();

    $data = new FunctionDefinitionData(
        internal_name: 'write_thing',
        display_name: 'Write Thing',
        description: 'Writes a thing',
        parameters_schema: ['type' => 'object', 'properties' => []],
        webhook_url: 'https://hooks.example.test/fn',
        webhook_method: FunctionWebhookMethod::Post,
        auth_type: FunctionAuthType::Hmac,     // no fresh secret provided
        auth_header_name: null,
        auth_value: null,
        requires_approval: new Optional,
    );

    app(UpdateAgentFunctionAction::class)->execute($function, $data);

    $function->refresh();
    // The old bearer token does not survive the auth_type change: the hmac
    // secret starts empty until the user enters one.
    expect($function->auth_type)->toBe(FunctionAuthType::Hmac)
        ->and($function->auth_config)->toBe(['secret' => '']);
});

it('REPLACES the stored secret when auth_type changes AND a new value is provided', function (): void {
    $function = uafFunction();

    $data = new FunctionDefinitionData(
        internal_name: 'write_thing',
        display_name: 'Write Thing',
        description: 'Writes a thing',
        parameters_schema: ['type' => 'object', 'properties' => []],
        webhook_url: 'https://hooks.example.test/fn',
        webhook_method: FunctionWebhookMethod::Post,
        auth_type: FunctionAuthType::Hmac,
        auth_header_name: null,
        auth_value: 'new-hmac-secret',
        requires_approval: new Optional,
    );

    app(UpdateAgentFunctionAction::class)->execute($function, $data);

    expect($function->refresh()->auth_config)->toMatchArray(['secret' => 'new-hmac-secret']);
});
