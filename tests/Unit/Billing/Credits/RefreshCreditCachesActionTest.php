<?php

use App\Identity\Workspace\Models\User;
use Domain\Billing\Actions\Credits\FlushTokenUsageBufferAction;
use Domain\Billing\Actions\Credits\RefreshCreditCachesAction;
use Domain\Billing\Listeners\RecordLlmTokenUsage;
use Domain\Billing\Models\UserCredit;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;

beforeEach(fn () => Cache::flush());

it('rebuilds the cached counters of every workspace with a running cycle', function (): void {
    $active = User::factory()->workspace()->create();
    $credit = UserCredit::factory()->create([
        'user_id' => $active->id,
        'messages' => 100,
        'started_at' => now()->subDay(),
        'ends_at' => now()->addDays(20),
    ]);
    $credit->usage()->create(['messages' => 12]);

    $lapsed = User::factory()->workspace()->create();
    UserCredit::factory()->create([
        'user_id' => $lapsed->id,
        'started_at' => now()->subMonths(2),
        'ends_at' => now()->subMonth(),
    ]);

    // The test database is seeded, so other workspaces may be refreshed too.
    expect(app(RefreshCreditCachesAction::class)->execute())->toBeGreaterThanOrEqual(1)
        ->and(Cache::get("workspace:{$active->id}:credit:messages:sent"))->toBe(12)
        ->and(Cache::has("workspace:{$lapsed->id}:can-send-messages"))->toBeFalse();
});

it('flushes buffered token counts into the running cycle and empties the buffer', function (): void {
    Redis::del(RecordLlmTokenUsage::BUFFER_KEY);
    $owner = User::factory()->workspace()->create();
    $credit = UserCredit::factory()->create(['user_id' => $owner->id, 'ends_at' => now()->addDays(5)]);
    $credit->usage()->create();

    Redis::hincrby(RecordLlmTokenUsage::BUFFER_KEY, (string) $owner->id, 640);

    try {
        expect(app(FlushTokenUsageBufferAction::class)->execute())->toBe(1)
            ->and($credit->usage()->value('tokens'))->toBe(640)
            ->and(Redis::hlen(RecordLlmTokenUsage::BUFFER_KEY))->toBe(0);
    } finally {
        Redis::del(RecordLlmTokenUsage::BUFFER_KEY);
    }
});
