<?php

use App\Identity\Workspace\Models\User;
use Domain\Integration\Function\Models\AgentFunction;
use Tests\TestCase;

/**
 * Rules of webhook functions that hold on every path, not only the Functions
 * page (FunctionControllerTest): a name clash over the API is a 422, never a
 * 500; the approval gate follows the HTTP verb whether the function is saved
 * on the page or through the API; and the agent page never receives webhook
 * header values.
 */
beforeEach(function (): void {
    $this->owner = User::factory()->create();
    ownerTeam($this->owner);
});

/** The test, calling the API as the owner. */
function fwrAsOwnerApi(User $owner): TestCase
{
    return withApiToken($owner->createToken('account')->plainTextToken);
}

it('rejects an API rename onto a name the workspace already uses with a 422', function (): void {
    AgentFunction::factory()->forOwner($this->owner)->create(['internal_name' => 'taken_name']);
    $function = AgentFunction::factory()->forOwner($this->owner)->create(['internal_name' => 'other_name']);

    fwrAsOwnerApi($this->owner)
        ->patchJson(route('api.v1.functions.update', ['function' => $function->external_id]), ['internal_name' => 'taken_name'])
        ->assertUnprocessable()
        ->assertJsonPath('errors.internal_name.0', 'A function with this name already exists in your workspace.');

    expect($function->fresh()->internal_name)->toBe('other_name');
});

describe('the approval gate follows the HTTP verb on every path', function (): void {
    it('derives requires_approval from the verb when created through the API', function (string $method, bool $expected): void {
        fwrAsOwnerApi($this->owner)
            ->postJson(route('api.v1.functions.store'), [
                'internal_name' => 'fn_created',
                'description' => 'd',
                'parameters_schema' => ['type' => 'object', 'properties' => []],
                'webhook_url' => 'https://example.com/webhook',
                'webhook_method' => $method,
            ])
            ->assertSuccessful();

        expect(AgentFunction::query()->where('internal_name', 'fn_created')->sole()->requires_approval)->toBe($expected);
    })->with([
        'GET reads freely' => ['GET', false],
        'POST asks first' => ['POST', true],
        'DELETE asks first' => ['DELETE', true],
    ]);

    it('turns approval on when an API update switches a read into a write', function (): void {
        $function = AgentFunction::factory()->forOwner($this->owner)->create(['webhook_method' => 'GET', 'requires_approval' => false]);

        fwrAsOwnerApi($this->owner)
            ->patchJson(route('api.v1.functions.update', ['function' => $function->external_id]), ['webhook_method' => 'DELETE'])
            ->assertOk();

        expect($function->fresh()->requires_approval)->toBeTrue();
    });

    it('turns approval on when the Functions page switches a read into a write', function (): void {
        $function = AgentFunction::factory()->forOwner($this->owner)->create(['webhook_method' => 'GET', 'requires_approval' => false]);

        $this->actingAs($this->owner)
            ->patch(route('integrations.functions.update', ['function' => $function->external_id]), [
                'internal_name' => $function->internal_name,
                'description' => 'Now writes',
                'parameters_schema' => json_encode(['type' => 'object', 'properties' => []]),
                'webhook_url' => 'https://example.com/webhook',
                'webhook_method' => 'POST',
                'auth_type' => 'none',
            ])
            ->assertSessionHasNoErrors();

        expect($function->fresh()->requires_approval)->toBeTrue();
    });
});

describe('the agent page never receives webhook header values', function (): void {
    beforeEach(function (): void {
        $this->agent = makeTeamAgent($this->owner, 'Support');
        $this->function = AgentFunction::factory()->forOwner($this->owner)->create([
            'webhook_url' => 'https://example.com/webhook',
            'webhook_headers' => [['key' => 'X-Api-Key', 'value' => 'super-secret-header-value']],
        ]);
    });

    it('returns header names only when attaching a function to an agent', function (): void {
        $response = $this->actingAs($this->owner)
            ->postJson(route('agents.sources.functions.attach', ['agent' => $this->agent->external_id, 'function' => $this->function->external_id]))
            ->assertSuccessful()
            ->assertJsonPath('data.webhook_header_names', ['X-Api-Key'])
            ->assertJsonPath('data.has_webhook_headers', true);

        expect($response->getContent())->not->toContain('super-secret-header-value');
    });

    it('lists the agent page rows with the fields the page links and filters on, without header values', function (): void {
        $response = $this->actingAs($this->owner)
            ->getJson(route('agents.sources.functions.data', ['agent' => $this->agent->external_id]))
            ->assertOk()
            ->assertJsonPath('data.0.external_id', $this->function->external_id)
            ->assertJsonPath('data.0.webhook_url', 'https://example.com/webhook')
            ->assertJsonPath('data.0.webhook_method', 'POST')
            ->assertJsonPath('data.0.is_attached', false);

        expect($response->getContent())->not->toContain('super-secret-header-value');
    });
});
