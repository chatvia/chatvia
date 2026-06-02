<?php

use App\Workspace\Models\User;
use App\Billing\Models\UserAddon;
use App\Billing\Models\UserCredit;

it('returns 0 when user has no credit row', function (): void {
    $user = User::factory()->create();

    $available = $user->creditSystem()->consumableMessages(refresh: true);

    expect($available)->toBe(0);
});

it('returns 0 when the only credit is expired', function (): void {
    $user = User::factory()->create();

    UserCredit::factory()->create([
        'user_id' => $user->id,
        'messages' => 100,
        'started_at' => now()->subDays(40),
        'ends_at' => now()->subDay(),
    ]);

    $available = $user->creditSystem()->consumableMessages(refresh: true);

    expect($available)->toBe(0)
        ->and($user->credit)->toBeNull();
});

it('returns limit minus usage when active credit has consumed messages', function (): void {
    $user = User::factory()->create();

    $credit = UserCredit::factory()->create([
        'user_id' => $user->id,
        'messages' => 100,
        'started_at' => now()->subDays(5),
        'ends_at' => now()->addDays(25),
    ]);

    $credit->usage()->create(['messages' => 30]);

    $available = $user->creditSystem()->consumableMessages(refresh: true);

    expect($available)->toBe(70);
});

it('clamps at 0 when usage equals or exceeds limit', function (): void {
    $user = User::factory()->create();

    $credit = UserCredit::factory()->create([
        'user_id' => $user->id,
        'messages' => 100,
        'started_at' => now()->subDays(5),
        'ends_at' => now()->addDays(25),
    ]);

    $credit->usage()->create(['messages' => 150]);

    $available = $user->creditSystem()->consumableMessages(refresh: true);

    expect($available)->toBe(0);
});

it('adds active addons on top of remaining credit', function (): void {
    $user = User::factory()->create();

    $credit = UserCredit::factory()->create([
        'user_id' => $user->id,
        'messages' => 100,
        'started_at' => now()->subDays(5),
        'ends_at' => now()->addDays(25),
    ]);
    $credit->usage()->create(['messages' => 40]);

    UserAddon::factory()->create([
        'user_id' => $user->id,
        'type' => 'extra_message_credits',
        'amount' => 500,
        'started_at' => now(),
        'ends_at' => now()->addMonth(),
    ]);

    $available = $user->creditSystem()->consumableMessages(refresh: true);

    expect($available)->toBe(560);
});

it('ignores expired addons', function (): void {
    $user = User::factory()->create();

    $credit = UserCredit::factory()->create([
        'user_id' => $user->id,
        'messages' => 100,
        'started_at' => now()->subDays(5),
        'ends_at' => now()->addDays(25),
    ]);
    $credit->usage()->create(['messages' => 10]);

    UserAddon::factory()->create([
        'user_id' => $user->id,
        'type' => 'extra_message_credits',
        'amount' => 500,
        'started_at' => now()->subMonths(2),
        'ends_at' => now()->subDay(),
    ]);

    $available = $user->creditSystem()->consumableMessages(refresh: true);

    expect($available)->toBe(90);
});

it('sums multiple active addons', function (): void {
    $user = User::factory()->create();

    $credit = UserCredit::factory()->create([
        'user_id' => $user->id,
        'messages' => 100,
        'started_at' => now()->subDays(5),
        'ends_at' => now()->addDays(25),
    ]);
    $credit->usage()->create(['messages' => 0]);

    UserAddon::factory()->count(3)->create([
        'user_id' => $user->id,
        'type' => 'extra_message_credits',
        'amount' => 1000,
        'started_at' => now(),
        'ends_at' => now()->addMonth(),
    ]);

    $available = $user->creditSystem()->consumableMessages(refresh: true);

    expect($available)->toBe(3100);
});

it('returns the active credit and ignores expired ones via the credit relation', function (): void {
    $user = User::factory()->create();

    UserCredit::factory()->create([
        'user_id' => $user->id,
        'messages' => 50,
        'started_at' => now()->subDays(40),
        'ends_at' => now()->subDays(10),
    ]);

    $active = UserCredit::factory()->create([
        'user_id' => $user->id,
        'messages' => 200,
        'started_at' => now()->subDays(2),
        'ends_at' => now()->addDays(28),
    ]);

    $user->refresh();

    expect($user->credit)->not->toBeNull()
        ->and($user->credit->id)->toBe($active->id);
});
