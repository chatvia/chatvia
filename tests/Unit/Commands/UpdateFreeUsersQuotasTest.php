<?php

use App\Auth\Models\User;
use App\Billing\Models\UserCredit;
use App\Console\Commands\UpdateFreeUsersQuotas;
use Illuminate\Support\Facades\Event;

beforeEach(function (): void {
    Event::fake();
});

test('updates credit for user with expiring credit', function (): void {
    $quotaLimit = config('billing.packages.starter.quota_limits.monthly');

    $user = User::factory()->create(['trial_ends_at' => now()->addDays(7)]);

    // Create an expiring credit (expires within a day)
    $expiringCredit = UserCredit::factory()->create([
        'user_id' => $user->id,
        'messages' => 30,
        'started_at' => now()->subDays(29),
        'ends_at' => now()->addHours(12), // Expires within a day
    ]);

    $this->artisan(UpdateFreeUsersQuotas::class);

    $user->refresh();

    $latestCredit = $user->credit;

    expect($latestCredit)->not->toBeNull()
        ->and($latestCredit->id)->not->toBe($expiringCredit->id)
        ->and($latestCredit->messages)->toBe($quotaLimit['messages_limit'])
        ->and($latestCredit->ends_at->greaterThan(now()->addDays(29)))->toBeTrue()
        ->and($latestCredit->ends_at->lessThanOrEqualTo(now()->addMonth()))->toBeTrue();
});

test('updates credit for user without credit', function (): void {
    $quotaLimit = config('billing.packages.starter.quota_limits.monthly');

    $user = User::factory()->create(['trial_ends_at' => now()->addDays(7)]);

    $this->artisan(UpdateFreeUsersQuotas::class);

    $user->refresh();
    $latestCredit = $user->credit;

    expect($latestCredit)->not->toBeNull()
        ->and($latestCredit->messages)->toBe($quotaLimit['messages_limit'])
        ->and($latestCredit->ends_at->greaterThan(now()->addDays(29)))->toBeTrue()
        ->and($latestCredit->ends_at->lessThanOrEqualTo(now()->addMonth()))->toBeTrue();
});

test('does not update credit for user with valid credit', function (): void {
    Event::fake();

    $user = User::factory()->create(['trial_ends_at' => now()->addDays(7)]);

    $validCredit = UserCredit::factory()->create([
        'user_id' => $user->id,
        'messages' => 70,
        'started_at' => now()->subDays(10),
        'ends_at' => now()->addDays(20),
    ]);

    $this->artisan(UpdateFreeUsersQuotas::class);

    $user->refresh();
    $latestCredit = $user->credit;

    expect($latestCredit->id)->toBe($validCredit->id)
        ->and($latestCredit->messages)->toBe(70);
});
