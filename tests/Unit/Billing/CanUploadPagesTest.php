<?php

use App\Identity\Workspace\Models\User;

it('allows upload when remaining quota exceeds requested amount', function (): void {
    $user = User::factory()->create();
    $user->quota->update(['pages_limit' => 100]);

    expect($user->consumption()->canUploadPages(50))->toBeTrue();
});

it('allows upload at the exact boundary (remaining equals requested)', function (): void {
    $user = User::factory()->create();
    $user->quota->update(['pages_limit' => 100]);

    expect($user->consumption()->canUploadPages(100))->toBeTrue();
});

it('blocks upload when requested amount exceeds remaining quota', function (): void {
    $user = User::factory()->create();
    $user->quota->update(['pages_limit' => 100]);

    expect($user->consumption()->canUploadPages(101))->toBeFalse();
});
