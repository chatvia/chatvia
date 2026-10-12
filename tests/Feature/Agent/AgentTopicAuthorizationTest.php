<?php

use App\Identity\Workspace\Models\User;
use Domain\Agent\Models\Agent;
use Domain\Conversation\Topic\Models\Topic;

/*
 * Attaching an existing topic to an agent must respect topic ownership: a user
 * may attach a GLOBAL topic (workspace_id IS NULL) or one their workspace OWNS,
 * but never another workspace's PRIVATE topic. The store endpoint looks the
 * topic up by a body id, so the lookup must be scoped through
 * Topic::forWorkspace() — otherwise it is an IDOR that also leaks the topic
 * name/description back in the response.
 */

beforeEach(function (): void {
    // Pro plan grants the `topics` capability the route is gated behind.
    $this->agent = Agent::factory()->create();
    $this->owner = $this->agent->workspace->owner;
});

it('lets the owner attach their own topic', function (): void {
    $topic = Topic::create(['workspace_id' => $this->owner->workspace()->id, 'topic' => 'Mine', 'description' => 'x']);

    $this->actingAs($this->owner)
        ->postJson(route('agents.topics.store', $this->agent), ['topic_id' => $topic->id])
        ->assertOk();

    expect($this->agent->topics()->where('topics.id', $topic->id)->exists())->toBeTrue();
});

it('lets the owner attach a global topic', function (): void {
    $topic = Topic::create(['workspace_id' => null, 'topic' => 'Global', 'description' => 'x']);

    $this->actingAs($this->owner)
        ->postJson(route('agents.topics.store', $this->agent), ['topic_id' => $topic->id])
        ->assertOk();

    expect($this->agent->topics()->where('topics.id', $topic->id)->exists())->toBeTrue();
});

it('forbids attaching another account\'s private topic (IDOR)', function (): void {
    $other = User::factory()->create();
    $secret = Topic::create([
        'workspace_id' => $other->workspace()->id,
        'topic' => 'Secret Roadmap',
        'description' => 'confidential',
    ]);

    // The same answer as an id that does not exist: the topic rule looks
    // only inside the workspace (and at the shared system topics).
    $this->actingAs($this->owner)
        ->postJson(route('agents.topics.store', $this->agent), ['topic_id' => $secret->id])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('topic_id');

    expect($this->agent->topics()->where('topics.id', $secret->id)->exists())->toBeFalse();
});
