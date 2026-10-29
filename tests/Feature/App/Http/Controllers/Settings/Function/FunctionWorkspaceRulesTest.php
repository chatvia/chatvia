<?php

use App\Identity\Workspace\Models\User;
use Domain\Integration\Function\Models\AgentFunction;
use Tests\TestCase;

/**
 * Rules of webhook functions that hold on every path, not only the Functions
 * page (FunctionControllerTest): the approval gate follows the HTTP verb
 * whether the function is saved on the page or through the API, and the
 * agent page never receives webhook header values.
 */
beforeEach(function (): void {
    withLicense();

    $this->owner = User::factory()->create();
    ownerTeam($this->owner);
});

/** The test, calling the API as the owner. */
function fwrAsOwnerApi(User $owner): TestCase
{
    return withApiToken($owner->createToken('account')->plainTextToken);
}

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
]);

it('turns approval on when an update switches a read into a write, through the API or the Functions page', function (): void {
    $viaApi = AgentFunction::factory()->forOwner($this->owner)->create(['webhook_method' => 'GET', 'requires_approval' => false]);
    $viaPage = AgentFunction::factory()->forOwner($this->owner)->create(['webhook_method' => 'GET', 'requires_approval' => false]);

    $this->actingAs($this->owner)
        ->patch(route('integrations.functions.update', ['function' => $viaPage->external_id]), [
            'internal_name' => $viaPage->internal_name,
            'description' => 'Now writes',
            'parameters_schema' => json_encode(['type' => 'object', 'properties' => []]),
            'webhook_url' => 'https://example.com/webhook',
            'webhook_method' => 'POST',
            'auth_type' => 'none',
        ])
        ->assertSessionHasNoErrors();

    fwrAsOwnerApi($this->owner)
        ->patchJson(route('api.v1.functions.update', ['function' => $viaApi->external_id]), ['webhook_method' => 'DELETE'])
        ->assertOk();

    expect($viaApi->fresh()->requires_approval)->toBeTrue()
        ->and($viaPage->fresh()->requires_approval)->toBeTrue();
});

it('sends the agent page header names only, never header values', function (): void {
    $agent = makeTeamAgent($this->owner, 'Support');
    $function = AgentFunction::factory()->forOwner($this->owner)->create([
        'webhook_url' => 'https://example.com/webhook',
        'webhook_headers' => [['key' => 'X-Api-Key', 'value' => 'super-secret-header-value']],
    ]);

    $list = $this->actingAs($this->owner)
        ->getJson(route('agents.sources.functions.data', ['agent' => $agent->external_id]))
        ->assertOk()
        ->assertJsonPath('data.0.external_id', $function->external_id)
        ->assertJsonPath('data.0.webhook_url', 'https://example.com/webhook')
        ->assertJsonPath('data.0.webhook_method', 'POST')
        ->assertJsonPath('data.0.is_attached', false);

    $attach = $this->actingAs($this->owner)
        ->postJson(route('agents.sources.functions.attach', ['agent' => $agent->external_id, 'function' => $function->external_id]))
        ->assertSuccessful()
        ->assertJsonPath('data.webhook_header_names', ['X-Api-Key'])
        ->assertJsonPath('data.has_webhook_headers', true);

    expect($list->getContent())->not->toContain('super-secret-header-value')
        ->and($attach->getContent())->not->toContain('super-secret-header-value');
});
