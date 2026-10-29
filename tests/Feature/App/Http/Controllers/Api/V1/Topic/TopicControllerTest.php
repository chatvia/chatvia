<?php

use Domain\Conversation\Topic\Models\Topic;
use Tests\Support\Api\ActsAsApiOwner;

/**
 * An agent's conversation topics through the public API: attaching a system
 * topic by id once (a repeat or another workspace's topic is a field error),
 * and renaming a workspace topic but never a system one.
 */
uses(ActsAsApiOwner::class);

beforeEach(function (): void {
    $this->actAsApiOwner();
    $this->uri = "/api/v1/agents/{$this->agent->external_id}/topics";
});

it("attaches a system topic by id once, refusing the repeat and another workspace's topic", function (): void {
    $system = Topic::query()->system()->firstOrFail();
    $foreign = Topic::factory()->create();

    $this->withToken($this->token)
        ->postJson($this->uri, ['topic_id' => $system->external_id])
        ->assertCreated()
        ->assertJsonPath('data.is_system', true);
    $this->withToken($this->token)
        ->postJson($this->uri, ['topic_id' => $system->external_id])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['topic_id']);
    $this->withToken($this->token)
        ->postJson($this->uri, ['topic_id' => $foreign->external_id])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['topic_id']);

    expect($this->agent->topics()->where('topics.id', $system->id)->count())->toBe(1)
        ->and($this->agent->topics()->where('topics.id', $foreign->id)->exists())->toBeFalse();
});

it('renames a workspace topic but never a system one', function (): void {
    $mine = Topic::factory()->attachedTo($this->agent)->create(['topic' => 'Shipping']);
    $system = Topic::query()->system()->firstOrFail();
    $this->agent->topics()->attach($system->id);

    $this->withToken($this->token)
        ->patchJson("{$this->uri}/{$mine->external_id}", ['topic' => 'Delivery', 'description' => 'Delivery and tracking'])
        ->assertOk()
        ->assertJsonPath('data.topic', 'Delivery');
    $this->withToken($this->token)
        ->patchJson("{$this->uri}/{$system->external_id}", ['topic' => 'Hijacked', 'description' => 'Nope'])
        ->assertForbidden();

    expect($system->fresh()->topic)->not->toBe('Hijacked');
});
