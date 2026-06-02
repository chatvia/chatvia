<?php

use App\Workspace\Models\User;
use App\Chatter\Actions\DeleteChatterAction;

it('hard-deletes the chatter row', function (): void {
    $workspace = User::factory()->workspace()->create();
    $chatter = User::factory()->chatter($workspace)->create();

    app(DeleteChatterAction::class)->execute($chatter);

    expect(User::whereKey($chatter->id)->exists())->toBeFalse();
});

it('does not affect other chatters belonging to the same workspace', function (): void {
    $workspace = User::factory()->workspace()->create();
    $chatter = User::factory()->chatter($workspace)->create();
    $sibling = User::factory()->chatter($workspace)->create();

    app(DeleteChatterAction::class)->execute($chatter);

    expect(User::whereKey($chatter->id)->exists())->toBeFalse()
        ->and(User::whereKey($sibling->id)->exists())->toBeTrue();
});
