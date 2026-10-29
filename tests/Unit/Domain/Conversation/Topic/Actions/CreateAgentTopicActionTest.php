<?php

use App\Identity\Workspace\Models\User;
use Domain\Conversation\Topic\Actions\CreateAgentTopicAction;
use Domain\Conversation\Topic\Data\SaveTopicData;
use Support\Exceptions\DomainValidationException;

/**
 * A topic name is unique in its workspace: a second topic with the same
 * name is refused and nothing is added.
 */
it('rejects a second topic with the same name in the workspace', function (): void {
    $agent = createAgent(User::factory()->create());

    app(CreateAgentTopicAction::class)->execute($agent, new SaveTopicData(topic: 'Refunds', description: ''));

    expect(fn () => app(CreateAgentTopicAction::class)->execute($agent, new SaveTopicData(topic: 'Refunds', description: '')))
        ->toThrow(DomainValidationException::class);

    expect($agent->topics()->where('topic', 'Refunds')->count())->toBe(1);
});
