<?php

use App\Identity\Workspace\Models\User;
use Domain\Conversation\Topic\Actions\DetachTopicFromAgentAction;
use Domain\Conversation\Topic\Models\Topic;

/**
 * Detaching removes the agent ↔ topic link. A workspace-made topic is deleted
 * once no agent uses it; a system topic and a topic still used by another
 * agent are kept.
 */
it('deletes a workspace topic once its last agent lets it go', function (): void {
    $owner = User::factory()->create();
    $agent = createAgent($owner);
    $topic = Topic::create(['topic' => 'Refunds', 'description' => '', 'workspace_id' => $owner->workspace()->id]);
    $agent->topics()->attach($topic->id);

    app(DetachTopicFromAgentAction::class)->execute($agent, $topic);

    expect($agent->topics()->count())->toBe(0)
        ->and(Topic::query()->whereKey($topic->id)->exists())->toBeFalse();
});

it('keeps a workspace topic that another agent still uses', function (): void {
    $owner = User::factory()->create();
    $agent = createAgent($owner);
    $other = createAgent($owner);
    $topic = Topic::create(['topic' => 'Refunds', 'description' => '', 'workspace_id' => $owner->workspace()->id]);
    $agent->topics()->attach($topic->id);
    $other->topics()->attach($topic->id);

    app(DetachTopicFromAgentAction::class)->execute($agent, $topic);

    expect($agent->topics()->count())->toBe(0)
        ->and($other->topics()->whereKey($topic->id)->exists())->toBeTrue()
        ->and(Topic::query()->whereKey($topic->id)->exists())->toBeTrue();
});

it('never deletes a system topic', function (): void {
    $agent = createAgent(User::factory()->create());
    $topic = Topic::create(['topic' => 'General', 'description' => 'System topic']);
    $agent->topics()->attach($topic->id);

    app(DetachTopicFromAgentAction::class)->execute($agent, $topic);

    expect($agent->topics()->count())->toBe(0)
        ->and(Topic::query()->whereKey($topic->id)->exists())->toBeTrue();
});
