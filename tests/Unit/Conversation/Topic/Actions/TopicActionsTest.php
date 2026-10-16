<?php

use App\Identity\Workspace\Models\User;
use Domain\Conversation\Topic\Actions\CreateAgentTopicAction;
use Domain\Conversation\Topic\Actions\UpdateTopicAction;
use Domain\Conversation\Topic\Exceptions\SystemTopicProtectedException;
use Domain\Conversation\Topic\Models\Topic;
use Support\Exceptions\DomainValidationException;

/**
 * Topics are a workspace resource: they are created under the agent's
 * workspace no matter which teammate creates them, so every check on them
 * must key to the agent's workspace too — never the acting user. System
 * topics (no workspace) are read-only and raise the dedicated exception the
 * controller maps to 403.
 */
it('creates the topic under the agent workspace and attaches it to the agent', function (): void {
    $owner = User::factory()->create();
    $agent = createAgent($owner);

    $topic = app(CreateAgentTopicAction::class)->execute($agent, 'Refunds', 'Refund questions');

    expect($topic->workspace_id)->toBe($owner->workspace()->id)
        ->and($agent->topics()->whereKey($topic->id)->exists())->toBeTrue();
});

it('rejects a second topic with the same name in the workspace', function (): void {
    $agent = createAgent(User::factory()->create());

    app(CreateAgentTopicAction::class)->execute($agent, 'Refunds', '');

    expect(fn () => app(CreateAgentTopicAction::class)->execute($agent, 'Refunds', ''))
        ->toThrow(DomainValidationException::class);
});

it('lets anyone managing the agent edit a workspace topic', function (): void {
    $agent = createAgent(User::factory()->create());
    $topic = app(CreateAgentTopicAction::class)->execute($agent, 'Refunds', 'Old');

    // No acting user is involved: a teammate editing it goes through the
    // same workspace-keyed check as the owner.
    $updated = app(UpdateTopicAction::class)->execute($agent, $topic, 'Refund requests', 'New');

    expect($updated->fresh()->topic)->toBe('Refund requests')
        ->and($updated->fresh()->description)->toBe('New');
});

it('refuses to edit a topic owned by another workspace', function (): void {
    $agent = createAgent(User::factory()->create());
    $foreign = app(CreateAgentTopicAction::class)->execute(createAgent(User::factory()->create()), 'Private', '');

    expect(fn () => app(UpdateTopicAction::class)->execute($agent, $foreign, 'Hijacked', ''))
        ->toThrow(DomainValidationException::class);

    expect($foreign->fresh()->topic)->toBe('Private');
});

it('raises SystemTopicProtectedException for a system topic', function (): void {
    $agent = createAgent(User::factory()->create());
    $system = Topic::create(['topic' => 'General', 'description' => 'System topic']);

    expect(fn () => app(UpdateTopicAction::class)->execute($agent, $system, 'Renamed', ''))
        ->toThrow(SystemTopicProtectedException::class);
});

it('rejects renaming a topic to a name the workspace already uses', function (): void {
    $agent = createAgent(User::factory()->create());
    app(CreateAgentTopicAction::class)->execute($agent, 'Billing', '');
    $topic = app(CreateAgentTopicAction::class)->execute($agent, 'Refunds', '');

    expect(fn () => app(UpdateTopicAction::class)->execute($agent, $topic, 'Billing', ''))
        ->toThrow(DomainValidationException::class, 'You already have a topic with this name');
});
