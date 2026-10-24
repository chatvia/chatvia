<?php

use App\Identity\Workspace\Models\User;
use Domain\Agent\Models\Agent;
use Domain\Conversation\Topic\Models\Topic;

/**
 * Managing the topics an agent uses: attaching an existing topic (the
 * workspace's own or a system one, never another workspace's private topic,
 * which the store looks up by a body id), renaming the agent's workspace
 * topics, and removing a topic (deleted when no other agent uses it).
 */
beforeEach(function (): void {
    $this->agent = Agent::factory()->create();
    $this->owner = $this->agent->workspace->owner;
});

it('attaches a topic of the workspace or a system topic', function (Closure $workspaceOf): void {
    $topic = Topic::factory()->create(['workspace_id' => $workspaceOf($this->agent)]);

    $this->actingAs($this->owner)
        ->postJson(route('agents.topics.store', $this->agent), ['topic_id' => $topic->id])
        ->assertOk();

    expect($this->agent->topics()->where('topics.id', $topic->id)->exists())->toBeTrue();
})->with([
    'the workspace\'s own' => fn (Agent $agent): int => $agent->workspace_id,
    'a system topic' => fn (): null => null,
]);

it('refuses another workspace\'s private topic like an id that does not exist', function (): void {
    $secret = Topic::factory()->create(['workspace_id' => User::factory()->create()->workspace()->id, 'topic' => 'Secret Roadmap']);

    // The topic rule looks only inside the workspace (and at the shared
    // system topics), so the name never leaks back in the answer.
    $this->actingAs($this->owner)
        ->postJson(route('agents.topics.store', $this->agent), ['topic_id' => $secret->id])
        ->assertJsonValidationErrors('topic_id')
        ->assertJsonMissing(['topic' => 'Secret Roadmap']);

    expect($this->agent->topics()->where('topics.id', $secret->id)->exists())->toBeFalse();
});

it('renames a topic the agent uses', function (): void {
    $topic = Topic::factory()->attachedTo($this->agent)->create(['topic' => 'Refunds']);

    $this->actingAs($this->owner)
        ->patchJson(route('agents.topics.update', [$this->agent, $topic]), ['topic' => 'Money back', 'description' => 'Refund requests'])
        ->assertOk()
        ->assertJsonPath('topic.topic', 'Money back');

    expect($topic->fresh())
        ->topic->toBe('Money back')
        ->description->toBe('Refund requests');
});

it('refuses to rename a topic the agent does not use, a system topic, or to a name the workspace has', function (): void {
    $unused = Topic::factory()->create(['workspace_id' => $this->agent->workspace_id, 'topic' => 'Unused']);
    $system = Topic::factory()->create(['workspace_id' => null]);
    $this->agent->topics()->attach($system->id);
    $refunds = Topic::factory()->attachedTo($this->agent)->create(['topic' => 'Refunds']);
    Topic::factory()->attachedTo($this->agent)->create(['topic' => 'Shipping']);

    $this->actingAs($this->owner)
        ->patchJson(route('agents.topics.update', [$this->agent, $unused]), ['topic' => 'Renamed', 'description' => 'x'])
        ->assertNotFound();
    $this->actingAs($this->owner)
        ->patchJson(route('agents.topics.update', [$this->agent, $system]), ['topic' => 'Renamed', 'description' => 'x'])
        ->assertForbidden();
    $this->actingAs($this->owner)
        ->patchJson(route('agents.topics.update', [$this->agent, $refunds]), ['topic' => 'Shipping', 'description' => 'x'])
        ->assertJsonValidationErrors(['topic']);

    expect($unused->fresh()->topic)->toBe('Unused')
        ->and($system->fresh()->topic)->not->toBe('Renamed')
        ->and($refunds->fresh()->topic)->toBe('Refunds');
});

it('removes a topic from the agent, and deletes a workspace topic no other agent uses', function (): void {
    $only = Topic::factory()->attachedTo($this->agent)->create();
    $shared = Topic::factory()->attachedTo($this->agent)->create();
    createAgent($this->owner)->topics()->attach($shared->id);

    $this->actingAs($this->owner)->deleteJson(route('agents.topics.destroy', [$this->agent, $only]))->assertOk();
    $this->actingAs($this->owner)->deleteJson(route('agents.topics.destroy', [$this->agent, $shared]))->assertOk();

    expect($this->agent->topics()->count())->toBe(0)
        ->and(Topic::query()->whereKey($only->id)->exists())->toBeFalse()
        ->and(Topic::query()->whereKey($shared->id)->exists())->toBeTrue();
});
