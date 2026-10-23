<?php

use App\Identity\ApiKey\Actions\CreateApiKeyAction;
use App\Identity\ApiKey\Data\CreateApiKeyData;
use App\Identity\Workspace\Models\User;
use Domain\Security\Events\SecurityEventOccurred;
use Illuminate\Support\Facades\Event;

/**
 * CreateApiKeyAction hands back a new token once with its plain-text value
 * and writes an `api_key.created` entry to the workspace's audit log.
 */
it('creates a token for the user and returns the plaintext payload', function (): void {
    $user = User::factory()->create();

    $result = app(CreateApiKeyAction::class)->execute(new CreateApiKeyData(
        user: $user,
        name: 'My CLI key',
    ));

    expect($result->name)->toBe('My CLI key')
        ->and($result->plaintext)->toStartWith($result->id.'|')
        ->and($user->tokens()->whereKey($result->id)->value('name'))->toBe('My CLI key');
});

it('audits the new token for the user workspace', function (): void {
    Event::fake([SecurityEventOccurred::class]);
    $user = User::factory()->employee()->create();

    $result = app(CreateApiKeyAction::class)->execute(new CreateApiKeyData(user: $user, name: 'Deploy key'));

    Event::assertDispatchedTimes(SecurityEventOccurred::class, 1);
    Event::assertDispatched(SecurityEventOccurred::class, fn (SecurityEventOccurred $event): bool => $event->action === 'api_key.created'
        && $event->workspaceId === $user->workspace()->id
        && $event->userId === $user->id
        && $event->targetType === 'api_token'
        && $event->targetId === (string) $result->id
        && $event->targetLabel === 'Deploy key');
});
