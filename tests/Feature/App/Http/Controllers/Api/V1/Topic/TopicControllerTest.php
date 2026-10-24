<?php

use Domain\Conversation\Topic\Models\Topic;
use Tests\Support\Api\ActsAsApiOwner;

/**
 * An agent's conversation topics through the public API: listing the
 * agent's (or every attachable one), attaching a system or workspace topic
 * by id, creating a workspace topic, renaming one (never a system topic) and
 * detaching one. The Topic Actions own what happens to a topic no agent uses
 * any more.
 */
uses(ActsAsApiOwner::class);

beforeEach(function (): void {
    $this->actAsApiOwner();
    $this->uri = "/api/v1/agents/{$this->agent->external_id}/topics";
});

it("lists the agent's topics, and every attachable one with scope=available", function (): void {
    $mine = Topic::factory()->attachedTo($this->agent)->create();

    $this->withToken($this->token)
        ->getJson($this->uri)
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $mine->external_id)
        ->assertJsonPath('data.0.is_system', false);

    $available = $this->withToken($this->token)->getJson("{$this->uri}?scope=available&per_page=100")->assertOk();

    expect(collect($available->json('data'))->pluck('is_system')->unique()->sort()->values()->all())->toBe([false, true]);
});

it('attaches a system topic by id once, and refuses the repeat with a field error', function (): void {
    $system = Topic::query()->system()->firstOrFail();

    $this->withToken($this->token)
        ->postJson($this->uri, ['topic_id' => $system->external_id])
        ->assertCreated()
        ->assertJsonPath('data.is_system', true);

    $this->withToken($this->token)
        ->postJson($this->uri, ['topic_id' => $system->external_id])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['topic_id']);
});

it("rejects attaching another workspace's topic by id", function (): void {
    // A new workspace's topic, by the factory's default.
    $foreign = Topic::factory()->create();

    $this->withToken($this->token)
        ->postJson($this->uri, ['topic_id' => $foreign->external_id])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['topic_id']);
});

it('creates a workspace topic and refuses a duplicate name with a field error', function (): void {
    $this->withToken($this->token)
        ->postJson($this->uri, ['topic' => 'Warranty', 'description' => 'Warranty questions'])
        ->assertCreated()
        ->assertJsonPath('data.topic', 'Warranty')
        ->assertJsonPath('data.is_system', false);

    $this->withToken($this->token)
        ->postJson($this->uri, ['topic' => 'Warranty', 'description' => 'Again'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['topic']);
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

it('detaches a topic from the agent', function (): void {
    $mine = Topic::factory()->attachedTo($this->agent)->create();

    $this->withToken($this->token)->deleteJson("{$this->uri}/{$mine->external_id}")->assertNoContent();

    expect($this->agent->topics()->where('topics.id', $mine->id)->exists())->toBeFalse();
});

it('404s updating a topic the agent does not use', function (): void {
    $unattached = Topic::factory()->create(['workspace_id' => $this->agent->workspace_id]);

    $this->withToken($this->token)
        ->patchJson("{$this->uri}/{$unattached->external_id}", ['topic' => 'Renamed', 'description' => 'Still idle'])
        ->assertNotFound();
});
