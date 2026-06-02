<?php

use App\Workspace\Models\User;
use Domain\Function\Models\AgentFunction;

beforeEach(function (): void {
    $this->user = User::factory()->create();
});

function makeFunction(User $user, array $overrides = []): AgentFunction
{
    return $user->functions()->create(array_merge([
        'name' => 'get_order_status',
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
        'name' => 'get_order_status',
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
        ->patch(route('settings.functions.update', ['external_id' => $fn->external_id]), updatePayload([
            'description' => 'Brand new description',
            'webhook_method' => 'GET',
        ]))
        ->assertRedirect();

    $fresh = $fn->fresh();
    expect($fresh->description)->toBe('Brand new description');
    expect($fresh->webhook_method)->toBe('GET');
});

it('keeps the existing secret when auth_value is left blank', function (): void {
    $fn = makeFunction($this->user, [
        'auth_type' => 'bearer',
        'auth_config' => ['token' => 'secret-123'],
    ]);

    $this->actingAs($this->user)
        ->patch(route('settings.functions.update', ['external_id' => $fn->external_id]), updatePayload([
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
        ->patch(route('settings.functions.update', ['external_id' => $fn->external_id]), updatePayload([
            'auth_type' => 'bearer',
            'auth_value' => 'new-token',
        ]))
        ->assertRedirect();

    expect($fn->fresh()->auth_config)->toBe(['token' => 'new-token']);
});

it('requires a credential when switching to an auth type with no stored secret', function (): void {
    $fn = makeFunction($this->user);

    $this->actingAs($this->user)
        ->patch(route('settings.functions.update', ['external_id' => $fn->external_id]), updatePayload([
            'auth_type' => 'bearer',
            'auth_value' => '',
        ]))
        ->assertSessionHasErrors('auth_value');
});

it('rejects a duplicate name from another function', function (): void {
    makeFunction($this->user, ['name' => 'existing_fn']);
    $fn = makeFunction($this->user, ['name' => 'editable_fn']);

    $this->actingAs($this->user)
        ->patch(route('settings.functions.update', ['external_id' => $fn->external_id]), updatePayload([
            'name' => 'existing_fn',
        ]))
        ->assertSessionHasErrors('name');
});

it('cannot update a function owned by another user', function (): void {
    $other = User::factory()->create();
    $fn = makeFunction($other);

    $this->actingAs($this->user)
        ->patch(route('settings.functions.update', ['external_id' => $fn->external_id]), updatePayload())
        ->assertForbidden();
});

it('toggles the enabled flag', function (): void {
    $fn = makeFunction($this->user, ['is_enabled' => true]);

    $this->actingAs($this->user)
        ->post(route('settings.functions.toggle', ['external_id' => $fn->external_id]))
        ->assertRedirect();

    expect($fn->fresh()->is_enabled)->toBeFalse();
});
