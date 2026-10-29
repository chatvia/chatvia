<?php

use App\Identity\Workspace\Models\User;
use Domain\Agent\Models\Agent;
use Domain\Conversation\Topic\Models\Topic;

/**
 * Managing the topics an agent uses: attaching never reaches another
 * workspace's private topic (the store looks it up by a body id, only inside
 * the workspace and the shared system topics, so its name never leaks), and
 * removing a topic deletes it when no other agent uses it.
 */
beforeEach(function (): void {
    $this->agent = Agent::factory()->create();
    $this->owner = $this->agent->workspace->owner;
});

it('refuses another workspace\'s private topic like an id that does not exist', function (): void {
    $secret = Topic::factory()->create(['workspace_id' => User::factory()->create()->workspace()->id, 'topic' => 'Secret Roadmap']);

    $this->actingAs($this->owner)
        ->postJson(route('agents.topics.store', $this->agent), ['topic_id' => $secret->id])
        ->assertJsonValidationErrors('topic_id')
        ->assertJsonMissing(['topic' => 'Secret Roadmap']);

    expect($this->agent->topics()->where('topics.id', $secret->id)->exists())->toBeFalse();
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
