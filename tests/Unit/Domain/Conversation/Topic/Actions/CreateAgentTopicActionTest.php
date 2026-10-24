<?php

use App\Identity\Workspace\Models\User;
use Domain\Conversation\Topic\Actions\CreateAgentTopicAction;
use Domain\Conversation\Topic\Data\SaveTopicData;
use Support\Exceptions\DomainValidationException;

/**
 * A new topic is a workspace resource: it is made under the agent's
 * workspace, whoever creates it, and given to the agent. A name is unique in
 * its workspace.
 */
it('creates the topic under the agent workspace and attaches it to the agent', function (): void {
    $owner = User::factory()->create();
    $agent = createAgent($owner);

    $topic = app(CreateAgentTopicAction::class)->execute($agent, new SaveTopicData(topic: 'Refunds', description: 'Refund questions'));

    expect($topic->workspace_id)->toBe($owner->workspace()->id)
        ->and($agent->topics()->whereKey($topic->id)->exists())->toBeTrue();
});

it('rejects a second topic with the same name in the workspace', function (): void {
    $agent = createAgent(User::factory()->create());

    app(CreateAgentTopicAction::class)->execute($agent, new SaveTopicData(topic: 'Refunds', description: ''));

    expect(fn () => app(CreateAgentTopicAction::class)->execute($agent, new SaveTopicData(topic: 'Refunds', description: '')))
        ->toThrow(DomainValidationException::class);

    expect($agent->topics()->where('topic', 'Refunds')->count())->toBe(1);
});
