<?php

use App\Identity\Workspace\Models\User;
use App\Identity\Workspace\Services\PasswordAuthenticator;
use Illuminate\Support\Facades\Hash;

it('returns the user when email and password match', function (): void {
    $user = User::factory()->create([
        'email' => 'match@example.test',
        'password' => Hash::make('right-password'),
    ]);

    $found = app(PasswordAuthenticator::class)->findUserByCredentials('match@example.test', 'right-password');

    expect($found?->id)->toBe($user->id);
});

it('returns null on a wrong password', function (): void {
    User::factory()->create([
        'email' => 'match@example.test',
        'password' => Hash::make('right-password'),
    ]);

    expect(app(PasswordAuthenticator::class)->findUserByCredentials('match@example.test', 'wrong-password'))
        ->toBeNull();
});

it('hashes once for an unknown email, like a wrong password would', function (): void {
    Hash::shouldReceive('make')->once()->andReturn('hashed');
    Hash::shouldReceive('check')->never();

    expect(app(PasswordAuthenticator::class)->findUserByCredentials('nobody@example.test', 'any-password'))
        ->toBeNull();
});

it('hashes once for an account without a password', function (): void {
    $user = User::factory()->create(['email' => 'social-only@example.test']);
    // Social-only accounts can carry an empty password; bypass the hashed cast.
    $user->setRawAttributes(array_merge($user->getAttributes(), ['password' => '']));
    $user->saveQuietly();

    Hash::shouldReceive('make')->once()->andReturn('hashed');
    Hash::shouldReceive('check')->never();

    expect(app(PasswordAuthenticator::class)->findUserByCredentials('social-only@example.test', 'any-password'))
        ->toBeNull();
});
