<?php

use Domain\Integration\Function\Models\AgentFunction;
use Tests\Support\Api\ActsAsApiOwner;

/**
 * The public API's functions (webhook tools): creating, listing and deleting
 * one, and refusing a parameters schema that is not an object schema.
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
