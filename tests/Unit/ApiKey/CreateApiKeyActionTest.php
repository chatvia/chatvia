<?php

use App\Identity\ApiKey\Actions\CreateApiKeyAction;
use App\Identity\ApiKey\Data\CreateApiKeyData;
use App\Identity\ApiKey\Data\CreatedApiKeyData;
use App\Identity\Workspace\Models\User;

it('creates a Sanctum token and returns the plaintext payload', function (): void {
    $user = User::factory()->create();

    $result = app(CreateApiKeyAction::class)->execute(new CreateApiKeyData(
        user: $user,
        name: 'My CLI key',
    ));

    expect($result)->toBeInstanceOf(CreatedApiKeyData::class)
        ->and($result->name)->toBe('My CLI key')
        ->and($result->plaintext)->not->toBeEmpty()
        ->and($result->id)->toBeGreaterThan(0);
});

it('persists the token row against the user', function (): void {
    $user = User::factory()->create();

    $result = app(CreateApiKeyAction::class)->execute(new CreateApiKeyData(
        user: $user,
        name: 'Token A',
    ));

    expect($user->tokens()->whereKey($result->id)->exists())->toBeTrue();
});

it('returns distinct plaintext values for separate calls', function (): void {
    $user = User::factory()->create();
    $action = app(CreateApiKeyAction::class);

    $first = $action->execute(new CreateApiKeyData(user: $user, name: 'A'));
    $second = $action->execute(new CreateApiKeyData(user: $user, name: 'B'));

    expect($first->plaintext)->not->toBe($second->plaintext)
        ->and($first->id)->not->toBe($second->id);
});
