<?php

use App\Identity\Workspace\Models\User;
use Domain\Conversation\Topic\Actions\UpdateTopicAction;
use Domain\Conversation\Topic\Data\SaveTopicData;
use Domain\Conversation\Topic\Exceptions\SystemTopicProtectedException;
use Domain\Conversation\Topic\Models\Topic;
use Support\Exceptions\DomainValidationException;

/**
 * Editing a topic is checked against the agent's workspace, never the acting
 * person: another workspace's topic is refused, a system topic (no
 * workspace) is read-only with the exception the controller maps to 403, and
 * a rename onto a name the workspace uses is refused.
 */
it('refuses to edit a topic owned by another workspace', function (): void {
    $agent = createAgent(User::factory()->create());
    $foreign = Topic::factory()->attachedTo(createAgent(User::factory()->create()))->create(['topic' => 'Private']);

    expect(fn () => app(UpdateTopicAction::class)->execute($agent, $foreign, new SaveTopicData(topic: 'Hijacked', description: '')))
        ->toThrow(DomainValidationException::class);

    expect($foreign->fresh()->topic)->toBe('Private');
});

it('refuses to edit a system topic', function (): void {
    $agent = createAgent(User::factory()->create());
    $system = Topic::factory()->create(['workspace_id' => null, 'topic' => 'General']);

    expect(fn () => app(UpdateTopicAction::class)->execute($agent, $system, new SaveTopicData(topic: 'Renamed', description: '')))
        ->toThrow(SystemTopicProtectedException::class);

    expect($system->fresh()->topic)->toBe('General');
});

it('rejects renaming a topic to a name the workspace already uses', function (): void {
    $agent = createAgent(User::factory()->create());
    Topic::factory()->attachedTo($agent)->create(['topic' => 'Billing']);
    $topic = Topic::factory()->attachedTo($agent)->create(['topic' => 'Refunds']);

    expect(fn () => app(UpdateTopicAction::class)->execute($agent, $topic, new SaveTopicData(topic: 'Billing', description: '')))
        ->toThrow(DomainValidationException::class, 'You already have a topic with this name');

    expect($topic->fresh()->topic)->toBe('Refunds');
});
