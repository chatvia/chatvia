<?php

use App\Identity\Workspace\Models\User;
use Domain\Conversation\Topic\Actions\AttachExistingTopicToAgentAction;
use Domain\Conversation\Topic\Models\Topic;
use Support\Exceptions\DomainValidationException;

/**
 * Attaching links an existing topic (a system topic or one of the workspace's
 * own) to an agent exactly once; a second attach is a validation error, not a
 * duplicate pivot row.
 */
it('attaches a system topic to the agent', function (): void {
    $agent = createAgent(User::factory()->create());
    $topic = Topic::factory()->create(['workspace_id' => null, 'topic' => 'Billing']);

    app(AttachExistingTopicToAgentAction::class)->execute($agent, $topic);

    expect($agent->topics()->whereKey($topic->id)->exists())->toBeTrue();
});

it('attaches a workspace topic to the agent', function (): void {
    $owner = User::factory()->create();
    $agent = createAgent($owner);
    $topic = Topic::factory()->create(['workspace_id' => $owner->workspace()->id, 'topic' => 'Refunds']);

    app(AttachExistingTopicToAgentAction::class)->execute($agent, $topic);

    expect($agent->topics()->pluck('topics.id')->all())->toBe([$topic->id]);
});

it('rejects a topic the agent already has, without adding a second link', function (): void {
    $agent = createAgent(User::factory()->create());
    $topic = Topic::factory()->create(['workspace_id' => null, 'topic' => 'Shipping']);

    app(AttachExistingTopicToAgentAction::class)->execute($agent, $topic);

    expect(fn () => app(AttachExistingTopicToAgentAction::class)->execute($agent, $topic))
        ->toThrow(DomainValidationException::class, 'Topic already associated with this agent');

    expect($agent->topics()->where('topics.id', $topic->id)->count())->toBe(1);
});
