<?php

use App\Identity\Workspace\Models\User;
use Domain\Integration\Function\Models\AgentFunction;
use Tests\Support\Api\ActsAsApiOwner;

/**
 * The public API's functions (webhook tools): creating, listing only the
 * workspace's own, showing, updating and deleting one, and the
 * parameters-schema rules on create and update.
 */
uses(ActsAsApiOwner::class);

beforeEach(function (): void {
    $this->actAsApiOwner();
});

it('creates a function with a POST webhook, lists it and deletes it', function (): void {
    $created = $this->withToken($this->token)
        ->postJson('/api/v1/functions', [
            'internal_name' => 'get_order_status',
            'description' => 'Look up an order by id',
            'parameters_schema' => ['type' => 'object', 'properties' => (object) []],
            'webhook_url' => 'https://acme.example.com/webhooks/orders',
        ])
        ->assertCreated()
        ->assertJsonPath('data.internal_name', 'get_order_status')
        ->assertJsonPath('data.webhook.method', 'POST')
        ->json('data');

    $this->withToken($this->token)->getJson('/api/v1/functions')->assertOk()->assertJsonCount(1, 'data');

    $this->withToken($this->token)->deleteJson('/api/v1/functions/'.$created['id'])->assertNoContent();

    expect(AgentFunction::query()->where('external_id', $created['id'])->exists())->toBeFalse();
});

it("never lists another workspace's functions", function (): void {
    AgentFunction::factory()->forOwner(User::factory()->create())->create();

    $this->withToken($this->token)->getJson('/api/v1/functions')->assertOk()->assertJsonCount(0, 'data');
});

it('shows a function of the workspace', function (): void {
    $function = AgentFunction::factory()->forOwner($this->owner)->create();

    $this->withToken($this->token)
        ->getJson("/api/v1/functions/{$function->external_id}")
        ->assertOk()
        ->assertJsonPath('data.id', $function->external_id);
});

it('rejects a parameters schema that is not an object schema and creates nothing', function (): void {
    $this->withToken($this->token)
        ->postJson('/api/v1/functions', [
            'internal_name' => 'get_order_status',
            'description' => 'Get the order status',
            'parameters_schema' => ['type' => 'object', 'properties' => ['x' => ['type' => 'function']]],
            'webhook_url' => 'https://example.com/webhook',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('parameters_schema');

    expect($this->owner->workspace()->functions()->count())->toBe(0);
});

it('rejects a changed schema with an invalid property name', function (): void {
    $function = AgentFunction::factory()->forOwner($this->owner)->create();

    $this->withToken($this->token)
        ->patchJson("/api/v1/functions/{$function->external_id}", [
            'parameters_schema' => ['type' => 'object', 'properties' => ['1bad' => ['type' => 'string']]],
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('parameters_schema');
});

it('accepts a stored schema that fails the shape rules when it is sent back unchanged', function (): void {
    $stored = ['type' => 'object', 'properties' => ['order-id' => ['type' => 'string']]];
    $function = AgentFunction::factory()->forOwner($this->owner)->create(['parameters_schema' => $stored]);

    $this->withToken($this->token)
        ->patchJson("/api/v1/functions/{$function->external_id}", ['description' => 'New description', 'parameters_schema' => $stored])
        ->assertOk()
        ->assertJsonPath('data.description', 'New description');
});
