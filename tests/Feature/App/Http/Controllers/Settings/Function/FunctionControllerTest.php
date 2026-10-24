<?php

/**
 * Webhook functions on the Functions page (FunctionController): workspace
 * resources a teammate with account.functions lists, creates and edits like
 * the owner; a stored secret survives an edit that leaves it blank; the
 * parameters schema must be an object schema with valid property names
 * (unless the stored one is sent back unchanged); a name is unique in the
 * workspace; deleting a function takes it out of its agents' tool pool.
 */

use App\Identity\Workspace\Models\User;
use Domain\Agent\Models\Agent;
use Domain\Integration\Function\Enums\FunctionWebhookMethod;
use Domain\Integration\Function\Models\AgentFunction;
use Domain\Integration\Tool\Data\ToolEntry;
use Domain\Integration\Tool\Registry\ToolRegistry;
use Inertia\Testing\AssertableInertia;

beforeEach(function (): void {
    $this->owner = User::factory()->create();
    // The settings routes are gated by a team permission, which needs a team.
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

it('lists the workspace\'s functions to a teammate with account.functions', function (): void {
    $function = fncFunction($this->owner);
    $teammate = teammateWithPermissions($this->owner, ['account.functions']);

    $this->actingAs($teammate)
        ->get(route('integrations.functions.edit'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->has('functions', 1)
            ->where('functions.0.id', $function->external_id));
});

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

it('lets a teammate with account.functions edit a workspace function', function (): void {
    $function = fncFunction($this->owner);
    $teammate = teammateWithPermissions($this->owner, ['account.functions']);

    $this->actingAs($teammate)
        ->patch(route('integrations.functions.update', ['function' => $function->external_id]), fncForm([
            'description' => 'Edited by a teammate',
            'webhook_method' => 'GET',
        ]))
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect($function->fresh())
        ->description->toBe('Edited by a teammate')
        ->webhook_method->toBe(FunctionWebhookMethod::Get);
});

it('keeps the stored secret when auth_value is left blank', function (): void {
    $function = fncFunction($this->owner, ['auth_type' => 'bearer', 'auth_config' => ['token' => 'secret-123']]);

    $this->actingAs($this->owner)
        ->patch(route('integrations.functions.update', ['function' => $function->external_id]), fncForm([
            'description' => 'Edited without touching the token',
            'auth_type' => 'bearer',
            'auth_value' => '',
        ]))
        ->assertRedirect();

    expect($function->fresh())
        ->description->toBe('Edited without touching the token')
        ->auth_config->toBe(['token' => 'secret-123']);
});

it('replaces the secret when a new auth_value is given', function (): void {
    $function = fncFunction($this->owner, ['auth_type' => 'bearer', 'auth_config' => ['token' => 'old-token']]);

    $this->actingAs($this->owner)
        ->patch(route('integrations.functions.update', ['function' => $function->external_id]), fncForm([
            'auth_type' => 'bearer',
            'auth_value' => 'new-token',
        ]))
        ->assertRedirect();

    expect($function->fresh()->auth_config)->toBe(['token' => 'new-token']);
});

it('requires a credential when switching to an auth type with no stored secret', function (): void {
    $function = fncFunction($this->owner);

    $this->actingAs($this->owner)
        ->patch(route('integrations.functions.update', ['function' => $function->external_id]), fncForm([
            'auth_type' => 'bearer',
            'auth_value' => '',
        ]))
        ->assertSessionHasErrors('auth_value');
});

it('turns a rename onto a name the workspace already uses into a validation error', function (): void {
    fncFunction($this->owner, ['internal_name' => 'taken_name']);
    $function = fncFunction($this->owner, ['internal_name' => 'other_name']);
    $teammate = teammateWithPermissions($this->owner, ['account.functions']);

    $this->actingAs($teammate)
        ->patch(route('integrations.functions.update', ['function' => $function->external_id]), fncForm([
            'internal_name' => 'taken_name',
        ]))
        ->assertSessionHasErrors('internal_name');

    expect($function->fresh()->internal_name)->toBe('other_name');
});

it('rejects an edited schema with an invalid property name', function (): void {
    $function = fncFunction($this->owner);

    $this->actingAs($this->owner)
        ->patch(route('integrations.functions.update', ['function' => $function->external_id]), fncForm([
            'parameters_schema' => json_encode(['type' => 'object', 'properties' => ['order-id' => ['type' => 'string']]]),
        ]))
        ->assertSessionHasErrors('parameters_schema');
});

it('saves the other fields when a stored schema that fails the shape rules is sent back unchanged', function (): void {
    $stored = ['type' => 'object', 'properties' => ['order-id' => ['type' => 'string']]];
    $function = fncFunction($this->owner, ['parameters_schema' => $stored]);

    $this->actingAs($this->owner)
        ->patch(route('integrations.functions.update', ['function' => $function->external_id]), fncForm([
            'description' => 'Edited without touching the schema',
            'parameters_schema' => json_encode($stored, JSON_PRETTY_PRINT),
        ]))
        ->assertSessionHasNoErrors();

    expect($function->fresh()->description)->toBe('Edited without touching the schema');
});

it('takes a deleted function out of its agents\' cached tool pool', function (): void {
    $agent = Agent::factory()->create();
    $function = AgentFunction::factory()->create(['workspace_id' => $agent->workspace_id, 'internal_name' => 'doomed_fn']);
    $agent->attachFunction($function);
    $names = fn (): array => array_map(static fn (ToolEntry $entry): string => $entry->name, app(ToolRegistry::class)->for($agent));

    expect($names())->toContain('doomed_fn');

    $this->actingAs($agent->workspace->owner)
        ->delete(route('integrations.functions.destroy', ['function' => $function->external_id]))
        ->assertRedirect();

    expect(AgentFunction::query()->whereKey($function->id)->exists())->toBeFalse()
        ->and($names())->not->toContain('doomed_fn');
});
