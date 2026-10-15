<?php

use App\Identity\ApiKey\Actions\CreateApiKeyAction;
use App\Identity\ApiKey\Actions\DeleteApiKeyAction;
use App\Identity\ApiKey\Data\CreateApiKeyData;
use App\Identity\Workspace\Models\User;
use Domain\Security\Events\SecurityEventOccurred;
use Illuminate\Support\Facades\Event;

/**
 * DeleteApiKeyAction revokes one of the user's own tokens and audits it as
 * `api_key.deleted`. Someone else's token id deletes nothing and is not
 * audited.
 */
it('deletes the token belonging to the user and audits it', function (): void {
    $user = User::factory()->create();
    $created = app(CreateApiKeyAction::class)->execute(new CreateApiKeyData(
        user: $user,
        name: 'temp',
    ));
    Event::fake([SecurityEventOccurred::class]);

    app(DeleteApiKeyAction::class)->execute($user, $created->id);

    expect($user->tokens()->whereKey($created->id)->exists())->toBeFalse();

    Event::assertDispatchedTimes(SecurityEventOccurred::class, 1);
    Event::assertDispatched(SecurityEventOccurred::class, fn (SecurityEventOccurred $event): bool => $event->action === 'api_key.deleted'
        && $event->workspaceId === $user->workspace()->id
        && $event->userId === $user->id
        && $event->targetType === 'api_token'
        && $event->targetId === (string) $created->id);
});

it('neither deletes nor audits a token that belongs to another user', function (): void {
    $alice = User::factory()->create();
    $bob = User::factory()->create();
    $bobsToken = app(CreateApiKeyAction::class)->execute(new CreateApiKeyData(
        user: $bob,
        name: 'bobs',
    ));
    Event::fake([SecurityEventOccurred::class]);

    app(DeleteApiKeyAction::class)->execute($alice, $bobsToken->id);

    expect($bob->tokens()->whereKey($bobsToken->id)->exists())->toBeTrue();
    Event::assertNotDispatched(SecurityEventOccurred::class);
});
