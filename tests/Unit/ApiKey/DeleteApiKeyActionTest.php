<?php

use App\Identity\ApiKey\Actions\CreateApiKeyAction;
use App\Identity\ApiKey\Actions\DeleteApiKeyAction;
use App\Identity\ApiKey\Data\CreateApiKeyData;
use App\Identity\Workspace\Models\User;

it('deletes the token belonging to the user', function (): void {
    $user = User::factory()->create();
    $created = app(CreateApiKeyAction::class)->execute(new CreateApiKeyData(
        user: $user,
        name: 'temp',
    ));

    app(DeleteApiKeyAction::class)->execute($user, $created->id);

    expect($user->tokens()->whereKey($created->id)->exists())->toBeFalse();
});

it('does not delete tokens that belong to another user', function (): void {
    $alice = User::factory()->create();
    $bob = User::factory()->create();
    $bobsToken = app(CreateApiKeyAction::class)->execute(new CreateApiKeyData(
        user: $bob,
        name: 'bobs',
    ));

    app(DeleteApiKeyAction::class)->execute($alice, $bobsToken->id);

    expect($bob->tokens()->whereKey($bobsToken->id)->exists())->toBeTrue();
});
