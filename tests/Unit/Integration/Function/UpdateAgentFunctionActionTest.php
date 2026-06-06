<?php

use App\Identity\Workspace\Models\User;
use Domain\Integration\Function\Actions\UpdateAgentFunctionAction;
use Domain\Integration\Function\Data\UpdateAgentFunctionData;
use Domain\Integration\Function\Models\AgentFunction;
use Illuminate\Support\Str;

/**
 * UpdateAgentFunctionAction patches an AgentFunction (a webhook tool). The
 * two behaviours pinned here are the ones the architect audit flagged as
 * security-relevant and historically drift-prone:
 *  - When the caller omits `requires_approval` the stored value must not
 *    be reset to false silently (an API consumer's partial PATCH must not
 *    accidentally clear the approval gate the user set in the dashboard).
 *  - When `auth_type` changes (e.g. bearer → hmac) the stored secret of the
 *    previous type must be cleared and replaced from the submitted value.
 */
function uafBuildFunction(array $overrides = []): array
{
    $owner = User::factory()->create();

    $function = new AgentFunction;
    $function->user_id = $owner->id;
    $function->external_id = (string) Str::uuid();
    $function->internal_name = 'write_thing';
    $function->display_name = 'Write Thing';
    $function->description = 'Writes a thing';
    $function->parameters_schema = ['type' => 'object', 'properties' => []];
    $function->webhook_url = 'https://hooks.example.test/fn';
    $function->webhook_method = 'POST';
    $function->auth_type = 'bearer';
    $function->auth_config = ['token' => 'stored-secret-xxx'];
    $function->requires_approval = true;
    $function->is_enabled = true;

    foreach ($overrides as $k => $v) {
        $function->{$k} = $v;
    }

    $function->save();

    return [$owner, $function];
}

it('preserves requires_approval when the caller omits the field (null)', function (): void {
    [$owner, $function] = uafBuildFunction();

    $data = new UpdateAgentFunctionData(
        internal_name: 'write_thing',
        display_name: 'Write Thing',
        description: 'Writes a thing',
        parameters_schema: ['type' => 'object', 'properties' => []],
        webhook_url: 'https://hooks.example.test/fn',
        webhook_method: 'POST',
        auth_type: 'bearer',
        auth_value: null,
        auth_header_name: null,
        requires_approval: null,
    );

    app(UpdateAgentFunctionAction::class)->execute($owner, $function->external_id, $data);

    $function->refresh();
    expect($function->requires_approval)->toBeTrue();
});

it('updates requires_approval when the caller explicitly sends false', function (): void {
    [$owner, $function] = uafBuildFunction();

    $data = new UpdateAgentFunctionData(
        internal_name: 'write_thing',
        display_name: 'Write Thing',
        description: 'Writes a thing',
        parameters_schema: ['type' => 'object', 'properties' => []],
        webhook_url: 'https://hooks.example.test/fn',
        webhook_method: 'POST',
        auth_type: 'bearer',
        auth_value: null,
        auth_header_name: null,
        requires_approval: false,
    );

    app(UpdateAgentFunctionAction::class)->execute($owner, $function->external_id, $data);

    expect($function->refresh()->requires_approval)->toBeFalse();
});

it('CLEARS the stored secret when auth_type changes (bearer → hmac) without a fresh value', function (): void {
    [$owner, $function] = uafBuildFunction();

    $data = new UpdateAgentFunctionData(
        internal_name: 'write_thing',
        display_name: 'Write Thing',
        description: 'Writes a thing',
        parameters_schema: ['type' => 'object', 'properties' => []],
        webhook_url: 'https://hooks.example.test/fn',
        webhook_method: 'POST',
        auth_type: 'hmac',
        auth_value: null,     // no fresh secret provided
        auth_header_name: null,
        requires_approval: null,
    );

    app(UpdateAgentFunctionAction::class)->execute($owner, $function->external_id, $data);

    $function->refresh();
    // The OLD bearer token must not survive the auth_type change.
    expect($function->auth_type)->toBe('hmac')
        ->and($function->auth_config)
        ->not->toHaveKey('token');
});

it('REPLACES the stored secret when auth_type changes AND a new value is provided', function (): void {
    [$owner, $function] = uafBuildFunction();

    $data = new UpdateAgentFunctionData(
        internal_name: 'write_thing',
        display_name: 'Write Thing',
        description: 'Writes a thing',
        parameters_schema: ['type' => 'object', 'properties' => []],
        webhook_url: 'https://hooks.example.test/fn',
        webhook_method: 'POST',
        auth_type: 'hmac',
        auth_value: 'new-hmac-secret',
        auth_header_name: null,
        requires_approval: null,
    );

    app(UpdateAgentFunctionAction::class)->execute($owner, $function->external_id, $data);

    expect($function->refresh()->auth_config)->toMatchArray(['secret' => 'new-hmac-secret']);
});
