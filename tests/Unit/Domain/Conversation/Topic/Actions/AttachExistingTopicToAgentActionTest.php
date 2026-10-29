<?php

use App\Identity\Workspace\Models\User;
use Domain\Conversation\Topic\Actions\AttachExistingTopicToAgentAction;
use Domain\Conversation\Topic\Models\Topic;
use Support\Exceptions\DomainValidationException;

/**
 * Attaching links an existing topic to an agent exactly once; a second
 * attach is a validation error, not a duplicate pivot row.
 */
it('rejects a topic the agent already has, without adding a second link', function (): void {
    $agent = createAgent(User::factory()->create());
    $topic = Topic::factory()->create(['workspace_id' => null, 'topic' => 'Shipping']);

    app(AttachExistingTopicToAgentAction::class)->execute($agent, $topic);

    expect(fn () => app(AttachExistingTopicToAgentAction::class)->execute($agent, $topic))
        ->toThrow(DomainValidationException::class, 'Topic already associated with this agent');

    expect($agent->topics()->where('topics.id', $topic->id)->count())->toBe(1);
});
