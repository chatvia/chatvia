<?php

use App\Identity\Workspace\Models\User;
use Domain\Conversation\Topic\Actions\DetachTopicFromAgentAction;
use Domain\Conversation\Topic\Models\Topic;

/**
 * Detaching removes the agent ↔ topic link. A workspace-made topic is deleted
 * once no agent uses it; a system topic and a topic still used by another
 * agent are kept.
 */
it('deletes a workspace topic once its last agent lets it go, and keeps it while another agent uses it', function (): void {
    $owner = User::factory()->create();
    $agent = createAgent($owner);
    $other = createAgent($owner);
    $topic = Topic::factory()->create(['workspace_id' => $owner->workspace()->id, 'topic' => 'Refunds']);
    $agent->topics()->attach($topic->id);
    $other->topics()->attach($topic->id);

    app(DetachTopicFromAgentAction::class)->execute($agent, $topic);
    $keptWhileUsed = Topic::query()->whereKey($topic->id)->exists();
    app(DetachTopicFromAgentAction::class)->execute($other, $topic);

    expect($agent->topics()->count())->toBe(0)
        ->and($keptWhileUsed)->toBeTrue()
        ->and(Topic::query()->whereKey($topic->id)->exists())->toBeFalse();
});

it('never deletes a system topic', function (): void {
    $agent = createAgent(User::factory()->create());
    $topic = Topic::factory()->create(['workspace_id' => null, 'topic' => 'General']);
    $agent->topics()->attach($topic->id);

    app(DetachTopicFromAgentAction::class)->execute($agent, $topic);

    expect($agent->topics()->count())->toBe(0)
        ->and(Topic::query()->whereKey($topic->id)->exists())->toBeTrue();
});
