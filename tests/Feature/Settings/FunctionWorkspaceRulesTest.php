<?php

use App\Identity\Workspace\Models\User;
use Domain\Integration\Function\Actions\CreateAgentFunctionAction;
use Domain\Integration\Function\Data\CreateAgentFunctionData;
use Domain\Integration\Function\Models\AgentFunction;
use Domain\Membership\Team\Actions\Roles\CreateTeamRoleAction;
use Domain\Membership\Team\Data\SaveTeamRoleData;
use Domain\Membership\Team\Services\TeamService;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\PermissionRegistrar;

/**
 * Webhook functions are workspace resources: teammates with account.functions
 * see and edit the owner's functions, names are unique per workspace (a clash
 * is a validation error, never a 500), the approval gate follows the verb on
 * every path, and header values never reach the dashboard.
 */
beforeEach(function (): void {
    $this->owner = User::factory()->create();
    app(TeamService::class)->createDefaultTeam($this->owner);
});

/**
 * @param  list<string>  $permissions
 */
function functionsTeammate(User $owner, array $permissions): User
{
    $team = $owner->primaryTeam();

    $role = app(CreateTeamRoleAction::class)->execute(new SaveTeamRoleData(
        team: $team,
        name: 'Role-'.Str::random(10),
        permissions: $permissions,
        allAgents: true,
        agentExternalIds: [],
    ));

    $member = User::factory()->employee($owner)->create(['current_team_id' => $team->id]);
    $team->members()->attach($member->id, ['role_id' => $role->id]);

    app(PermissionRegistrar::class)->forgetCachedPermissions();

    return $member;
}

function workspaceFunction(User $owner, array $overrides = []): AgentFunction
{
    return $owner->functions()->create(array_merge([
        'internal_name' => 'get_order_status',
        'description' => 'Get the order status',
        'parameters_schema' => ['type' => 'object', 'properties' => []],
        'webhook_url' => 'https://example.com/webhook',
        'webhook_method' => 'POST',
        'auth_type' => 'none',
        'is_enabled' => true,
    ], $overrides));
}

function functionFormPayload(array $overrides = []): array
{
    return array_merge([
        'internal_name' => 'get_order_status',
        'description' => 'Get the order status',
        'parameters_schema' => json_encode(['type' => 'object', 'properties' => []]),
        'webhook_url' => 'https://example.com/webhook',
        'webhook_method' => 'POST',
        'auth_type' => 'none',
    ], $overrides);
}

describe('teammates work on the workspace owner\'s functions', function (): void {

    it('lists, toggles and updates the owner\'s functions for a teammate with account.functions', function (): void {
        $fn = workspaceFunction($this->owner);
        $member = functionsTeammate($this->owner, ['account.functions']);

        $this->actingAs($member)
            ->get(route('integrations.functions.edit'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->has('functions', 1)->where('functions.0.id', $fn->external_id));

        $this->actingAs($member)
            ->post(route('integrations.functions.toggle', ['external_id' => $fn->external_id]))
            ->assertRedirect();
        expect($fn->fresh()->is_enabled)->toBeFalse();

        $this->actingAs($member)
            ->patch(route('integrations.functions.update', ['external_id' => $fn->external_id]), functionFormPayload([
                'description' => 'Edited by a teammate',
            ]))
            ->assertRedirect()
            ->assertSessionHasNoErrors();
        expect($fn->fresh()->description)->toBe('Edited by a teammate');
    });

    it('creates a teammate\'s function under the owner', function (): void {
        $member = functionsTeammate($this->owner, ['account.functions']);

        $this->actingAs($member)
            ->post(route('integrations.functions.store'), functionFormPayload(['internal_name' => 'team_fn']))
            ->assertRedirect();

        expect(AgentFunction::where('internal_name', 'team_fn')->sole()->user_id)->toBe($this->owner->id);
    });
});

describe('function names are unique per workspace', function (): void {

    it('turns a teammate rename onto an existing name into a validation error', function (): void {
        workspaceFunction($this->owner, ['internal_name' => 'taken_name']);
        $fn = workspaceFunction($this->owner, ['internal_name' => 'other_name']);
        $member = functionsTeammate($this->owner, ['account.functions']);

        $this->actingAs($member)
            ->patch(route('integrations.functions.update', ['external_id' => $fn->external_id]), functionFormPayload([
                'internal_name' => 'taken_name',
            ]))
            ->assertSessionHasErrors('internal_name');

        expect($fn->fresh()->internal_name)->toBe('other_name');
    });

    it('rejects a duplicate in the create action itself', function (): void {
        workspaceFunction($this->owner, ['internal_name' => 'taken_name']);
        $member = functionsTeammate($this->owner, ['account.functions']);

        expect(fn () => app(CreateAgentFunctionAction::class)->execute(new CreateAgentFunctionData(
            user: $member,
            internal_name: 'taken_name',
            display_name: null,
            description: 'd',
            parameters_schema: ['type' => 'object', 'properties' => []],
            webhook_url: 'https://example.com/webhook',
            webhook_method: 'POST',
            auth_type: 'none',
            auth_config: null,
        )))->toThrow(ValidationException::class);
    });

    it('rejects an API rename onto an existing name with a 422', function (): void {
        workspaceFunction($this->owner, ['internal_name' => 'taken_name']);
        $fn = workspaceFunction($this->owner, ['internal_name' => 'other_name']);
        $token = $this->owner->createToken('account')->plainTextToken;

        $this->withHeaders(['Authorization' => 'Bearer '.$token, 'Accept' => 'application/json'])
            ->patchJson('/api/v1/functions/'.$fn->external_id, ['internal_name' => 'taken_name'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('internal_name');
    });
});

describe('the approval gate follows the HTTP verb on every path', function (): void {

    it('derives requires_approval from the verb when created through the API', function (string $method, bool $expected): void {
        $token = $this->owner->createToken('account')->plainTextToken;

        $this->withHeaders(['Authorization' => 'Bearer '.$token, 'Accept' => 'application/json'])
            ->postJson('/api/v1/functions', [
                'internal_name' => 'fn_'.strtolower($method),
                'description' => 'd',
                'parameters_schema' => ['type' => 'object', 'properties' => []],
                'webhook_url' => 'https://example.com/webhook',
                'webhook_method' => $method,
            ])
            ->assertSuccessful();

        expect(AgentFunction::where('internal_name', 'fn_'.strtolower($method))->sole()->requires_approval)->toBe($expected);
    })->with([
        'GET reads freely' => ['GET', false],
        'POST asks first' => ['POST', true],
        'DELETE asks first' => ['DELETE', true],
    ]);

    it('turns approval on when an API update switches a read into a write', function (): void {
        $fn = workspaceFunction($this->owner, ['webhook_method' => 'GET', 'requires_approval' => false]);
        $token = $this->owner->createToken('account')->plainTextToken;

        $this->withHeaders(['Authorization' => 'Bearer '.$token, 'Accept' => 'application/json'])
            ->patchJson('/api/v1/functions/'.$fn->external_id, ['webhook_method' => 'DELETE'])
            ->assertOk();

        expect($fn->fresh()->requires_approval)->toBeTrue();
    });

    it('turns approval on when a dashboard update switches a read into a write', function (): void {
        $fn = workspaceFunction($this->owner, ['webhook_method' => 'GET', 'requires_approval' => false]);

        $this->actingAs($this->owner)
            ->patch(route('integrations.functions.update', ['external_id' => $fn->external_id]), functionFormPayload([
                'webhook_method' => 'POST',
            ]))
            ->assertSessionHasNoErrors();

        expect($fn->fresh()->requires_approval)->toBeTrue();
    });
});

describe('the dashboard never receives webhook header values', function (): void {

    // The agent routes sit behind the billing gate.
    beforeEach(function (): void {
        $this->owner->billing()->firstOrCreate([]);
        $this->owner->billing->subscriptions()->create([
            'type' => 'default',
            'stripe_id' => 'sub_'.$this->owner->id,
            'stripe_status' => 'active',
            'stripe_price' => 'price_test',
            'quantity' => 1,
        ]);
        $this->owner->refresh();
    });

    it('returns header names only when attaching a function to an agent', function (): void {
        $agent = makeTeamAgent($this->owner, 'Support');
        $fn = workspaceFunction($this->owner, [
            'webhook_headers' => [['key' => 'X-Api-Key', 'value' => 'super-secret-header-value']],
        ]);

        $response = $this->actingAs($this->owner)
            ->postJson(route('agents.sources.functions.attach', ['agent' => $agent->external_id, 'function' => $fn->external_id]))
            ->assertSuccessful()
            ->assertJsonPath('data.webhook_header_names', ['X-Api-Key'])
            ->assertJsonPath('data.has_webhook_headers', true);

        expect($response->getContent())->not->toContain('super-secret-header-value');
    });

    it('lists the agent page rows with the fields the page links and filters on', function (): void {
        $agent = makeTeamAgent($this->owner, 'Support');
        $fn = workspaceFunction($this->owner);

        $this->actingAs($this->owner)
            ->getJson(route('agents.sources.functions.data', ['agent' => $agent->external_id]))
            ->assertOk()
            ->assertJsonPath('data.0.external_id', $fn->external_id)
            ->assertJsonPath('data.0.webhook_url', 'https://example.com/webhook')
            ->assertJsonPath('data.0.webhook_method', 'POST')
            ->assertJsonPath('data.0.is_attached', false);
    });
});
