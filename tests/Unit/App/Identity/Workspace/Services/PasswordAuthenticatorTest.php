<?php

use App\Identity\Workspace\Models\User;
use App\Identity\Workspace\Services\PasswordAuthenticator;
use Illuminate\Support\Facades\Hash;

/**
 * The dashboard's password check: the account when email and password match,
 * otherwise null, and a password hash is computed either way, so the answer
 * time does not tell which emails have an account.
 */
it('returns the account only when the email and password match', function (): void {
    $user = User::factory()->create([
        'email' => 'match@example.test',
        'password' => Hash::make('right-password'),
    ]);
    $authenticator = app(PasswordAuthenticator::class);

    expect($authenticator->findUserByCredentials('match@example.test', 'right-password')?->id)->toBe($user->id)
        ->and($authenticator->findUserByCredentials('match@example.test', 'wrong-password'))->toBeNull();
});

it('hashes once for an unknown email or an account without a password, like a wrong password would', function (): void {
    $user = User::factory()->create(['email' => 'social-only@example.test']);
    $user->setRawAttributes(array_merge($user->getAttributes(), ['password' => '']));
    $user->saveQuietly();
    Hash::shouldReceive('make')->twice()->andReturn('hashed');
    Hash::shouldReceive('check')->never();
    $authenticator = app(PasswordAuthenticator::class);

    expect($authenticator->findUserByCredentials('nobody@example.test', 'any-password'))->toBeNull()
        ->and($authenticator->findUserByCredentials('social-only@example.test', 'any-password'))->toBeNull();
});
