<?php

use App\Identity\Workspace\Models\User;
use Domain\Integration\Function\Enums\FunctionWebhookMethod;
use Domain\Integration\Function\Models\AgentFunction;
use Domain\Membership\Team\Services\TeamService;

beforeEach(function (): void {
    $this->user = User::factory()->create();
    // The settings routes are gated by a team permission, which requires the
    // user to have a current/primary team.
    app(TeamService::class)->createDefaultTeam($this->user);
});

function makeFunction(User $user, array $overrides = []): AgentFunction
{
    return $user->workspace()->functions()->create(array_merge([
        'internal_name' => 'get_order_status',
        'description' => 'Get the order status',
        'parameters_schema' => ['type' => 'object', 'properties' => ['order_id' => ['type' => 'string']]],
        'webhook_url' => 'https://example.com/webhook',
        'webhook_method' => 'POST',
        'auth_type' => 'none',
        'auth_config' => null,
        'is_enabled' => true,
    ], $overrides));
}

function updatePayload(array $overrides = []): array
{
    return array_merge([
        'internal_name' => 'get_order_status',
        'description' => 'Updated description',
        'parameters_schema' => json_encode(['type' => 'object', 'properties' => []]),
        'webhook_url' => 'https://example.com/webhook',
        'webhook_method' => 'POST',
        'auth_type' => 'none',
    ], $overrides);
}

it('updates basic fields', function (): void {
    $fn = makeFunction($this->user);

    $this->actingAs($this->user)
        ->patch(route('integrations.functions.update', ['external_id' => $fn->external_id]), updatePayload([
            'description' => 'Brand new description',
            'webhook_method' => 'GET',
        ]))
        ->assertRedirect();

    $fresh = $fn->fresh();
    expect($fresh->description)->toBe('Brand new description');
    expect($fresh->webhook_method)->toBe(FunctionWebhookMethod::Get);
});

it('keeps the existing secret when auth_value is left blank', function (): void {
    $fn = makeFunction($this->user, [
        'auth_type' => 'bearer',
        'auth_config' => ['token' => 'secret-123'],
    ]);

    $this->actingAs($this->user)
        ->patch(route('integrations.functions.update', ['external_id' => $fn->external_id]), updatePayload([
            'description' => 'Edited without touching the token',
            'auth_type' => 'bearer',
            'auth_value' => '',
        ]))
        ->assertRedirect();

    $fresh = $fn->fresh();
    expect($fresh->description)->toBe('Edited without touching the token');
    expect($fresh->auth_config)->toBe(['token' => 'secret-123']);
});

it('replaces the secret when a new auth_value is provided', function (): void {
    $fn = makeFunction($this->user, [
        'auth_type' => 'bearer',
        'auth_config' => ['token' => 'old-token'],
    ]);

    $this->actingAs($this->user)
        ->patch(route('integrations.functions.update', ['external_id' => $fn->external_id]), updatePayload([
            'auth_type' => 'bearer',
            'auth_value' => 'new-token',
        ]))
        ->assertRedirect();

    expect($fn->fresh()->auth_config)->toBe(['token' => 'new-token']);
});

it('requires a credential when switching to an auth type with no stored secret', function (): void {
    $fn = makeFunction($this->user);

    $this->actingAs($this->user)
        ->patch(route('integrations.functions.update', ['external_id' => $fn->external_id]), updatePayload([
            'auth_type' => 'bearer',
            'auth_value' => '',
        ]))
        ->assertSessionHasErrors('auth_value');
});

it('rejects a duplicate name from another function', function (): void {
    makeFunction($this->user, ['internal_name' => 'existing_fn']);
    $fn = makeFunction($this->user, ['internal_name' => 'editable_fn']);

    $this->actingAs($this->user)
        ->patch(route('integrations.functions.update', ['external_id' => $fn->external_id]), updatePayload([
            'internal_name' => 'existing_fn',
        ]))
        ->assertSessionHasErrors('internal_name');
});

it('hides a function of another workspace from an update', function (): void {
    $other = User::factory()->create();
    $fn = makeFunction($other);

    $this->actingAs($this->user)
        ->patch(route('integrations.functions.update', ['external_id' => $fn->external_id]), updatePayload())
        ->assertNotFound();
});

it('toggles the enabled flag', function (): void {
    $fn = makeFunction($this->user, ['is_enabled' => true]);

    $this->actingAs($this->user)
        ->post(route('integrations.functions.toggle', ['external_id' => $fn->external_id]))
        ->assertRedirect();

    expect($fn->fresh()->is_enabled)->toBeFalse();
});

it('rejects a parameters schema that is not an object schema on create', function (): void {
    $this->actingAs($this->user)
        ->post(route('integrations.functions.store'), updatePayload([
            'internal_name' => 'new_fn',
            'parameters_schema' => json_encode(['type' => 'string']),
        ]))
        ->assertSessionHasErrors('parameters_schema');

    expect($this->user->workspace()->functions()->count())->toBe(0);
});

it('creates a function with a well-formed object schema', function (): void {
    $this->actingAs($this->user)
        ->post(route('integrations.functions.store'), updatePayload([
            'internal_name' => 'new_fn',
            'parameters_schema' => json_encode([
                'type' => 'object',
                'properties' => ['order_id' => ['type' => 'string']],
                'required' => ['order_id'],
            ]),
        ]))
        ->assertSessionHasNoErrors();

    expect($this->user->workspace()->functions()->where('internal_name', 'new_fn')->exists())->toBeTrue();
});

it('rejects an edited schema with an invalid property name', function (): void {
    $fn = makeFunction($this->user);

    $this->actingAs($this->user)
        ->patch(route('integrations.functions.update', ['external_id' => $fn->external_id]), updatePayload([
            'parameters_schema' => json_encode(['type' => 'object', 'properties' => ['order-id' => ['type' => 'string']]]),
        ]))
        ->assertSessionHasErrors('parameters_schema');
});

it('saves other fields when a stored schema that fails the shape rules is sent back unchanged', function (): void {
    $stored = ['type' => 'object', 'properties' => ['order-id' => ['type' => 'string']]];
    $fn = makeFunction($this->user, ['parameters_schema' => $stored]);

    $this->actingAs($this->user)
        ->patch(route('integrations.functions.update', ['external_id' => $fn->external_id]), updatePayload([
            'description' => 'Edited without touching the schema',
            'parameters_schema' => json_encode($stored, JSON_PRETTY_PRINT),
        ]))
        ->assertSessionHasNoErrors();

    expect($fn->fresh()->description)->toBe('Edited without touching the schema');
});
