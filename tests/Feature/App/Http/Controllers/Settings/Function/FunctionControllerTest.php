<?php

use App\Identity\Workspace\Models\User;
use Domain\Integration\Function\Models\AgentFunction;

/**
 * Webhook functions on the Functions page (FunctionController): workspace
 * resources a teammate with account.functions creates like the owner; a
 * stored secret survives an edit that leaves it blank and is replaced by a
 * new one; the parameters schema must be an object schema.
 */
beforeEach(function (): void {
    $this->owner = User::factory()->create();
    ownerTeam($this->owner);
});

/** A function of the workspace, with an order-status schema. */
function fncFunction(User $owner, array $overrides = []): AgentFunction
{
    return AgentFunction::factory()->forOwner($owner)->create([
        'internal_name' => 'get_order_status',
        'description' => 'Get the order status',
        'parameters_schema' => ['type' => 'object', 'properties' => ['order_id' => ['type' => 'string']]],
        ...$overrides,
    ]);
}

/** The function form as the page posts it. */
function fncForm(array $overrides = []): array
{
    return [
        'internal_name' => 'get_order_status',
        'description' => 'Get the order status',
        'parameters_schema' => json_encode(['type' => 'object', 'properties' => []]),
        'webhook_url' => 'https://example.com/webhook',
        'webhook_method' => 'POST',
        'auth_type' => 'none',
        ...$overrides,
    ];
}

it('creates a teammate\'s function under the workspace, with its object schema', function (): void {
    $teammate = teammateWithPermissions($this->owner, ['account.functions']);

    $this->actingAs($teammate)
        ->post(route('integrations.functions.store'), fncForm([
            'internal_name' => 'team_fn',
            'parameters_schema' => json_encode([
                'type' => 'object',
                'properties' => ['order_id' => ['type' => 'string']],
                'required' => ['order_id'],
            ]),
        ]))
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect(AgentFunction::query()->where('internal_name', 'team_fn')->sole()->workspace_id)->toBe($this->owner->workspace()->id);
});

it('rejects a parameters schema that is not an object schema on create', function (): void {
    $this->actingAs($this->owner)
        ->post(route('integrations.functions.store'), fncForm([
            'internal_name' => 'new_fn',
            'parameters_schema' => json_encode(['type' => 'string']),
        ]))
        ->assertSessionHasErrors('parameters_schema');

    expect($this->owner->workspace()->functions()->count())->toBe(0);
});

it('keeps the stored secret when auth_value is left blank, and replaces it when a new one is given', function (): void {
    $function = fncFunction($this->owner, ['auth_type' => 'bearer', 'auth_config' => ['token' => 'secret-123']]);
    $update = fn (array $fields) => $this->actingAs($this->owner)
        ->patch(route('integrations.functions.update', ['function' => $function->external_id]), fncForm(['auth_type' => 'bearer', ...$fields]))
        ->assertRedirect();

    $update(['description' => 'Edited without touching the token', 'auth_value' => '']);

    expect($function->fresh())
        ->description->toBe('Edited without touching the token')
        ->auth_config->toBe(['token' => 'secret-123']);

    $update(['auth_value' => 'new-token']);

    expect($function->fresh()->auth_config)->toBe(['token' => 'new-token']);
});
