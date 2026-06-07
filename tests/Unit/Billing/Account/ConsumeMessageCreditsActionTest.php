<?php

use App\Identity\Workspace\Models\User;
use Domain\Billing\Actions\Account\ConsumeMessageCreditsAction;
use Domain\Billing\Events\MessageCreditsLow;
use Domain\Billing\Models\UserCredit;
use Domain\Llm\Models\Llm;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;

beforeEach(fn () => Cache::flush());

function activeCreditFor(User $user, int $messages = 100): UserCredit
{
    $credit = UserCredit::factory()->create([
        'user_id' => $user->id,
        'messages' => $messages,
        'started_at' => now()->subDay(),
        'ends_at' => now()->addDays(29),
    ]);

    $credit->usage()->create(['messages' => 0]);

    return $credit;
}

it('increments usage by the model credit cost', function (): void {
    $user = User::factory()->workspace()->create();
    $credit = activeCreditFor($user);
    $llm = Llm::factory()->create(['credits' => 4]);

    app(ConsumeMessageCreditsAction::class)->execute($user->id, $llm->id);

    expect((int) $credit->usage()->value('messages'))->toBe(4);
});

it('adds the model credit cost on top of existing usage', function (): void {
    $user = User::factory()->workspace()->create();
    $credit = activeCreditFor($user);
    $credit->usage()->update(['messages' => 10]);
    $llm = Llm::factory()->create(['credits' => 4]);

    app(ConsumeMessageCreditsAction::class)->execute($user->id, $llm->id);

    expect((int) $credit->usage()->value('messages'))->toBe(14);
});

it('dispatches MessageCreditsLow when the balance falls to the auto-recharge threshold', function (): void {
    Event::fake([MessageCreditsLow::class]);

    $user = User::factory()->workspace()->create();
    activeCreditFor($user, messages: 2);
    $user->billing()->updateOrCreate([], [
        'auto_recharge_enabled' => true,
        'auto_recharge_threshold' => 100,
        'auto_recharge_amount' => 1000,
    ]);
    $llm = Llm::factory()->create(['credits' => 1]);

    app(ConsumeMessageCreditsAction::class)->execute($user->id, $llm->id);

    Event::assertDispatched(MessageCreditsLow::class);
});

it('does not dispatch MessageCreditsLow when auto-recharge is disabled', function (): void {
    Event::fake([MessageCreditsLow::class]);

    $user = User::factory()->workspace()->create();
    activeCreditFor($user, messages: 2);
    $user->billing()->updateOrCreate([], ['auto_recharge_enabled' => false]);
    $llm = Llm::factory()->create(['credits' => 1]);

    app(ConsumeMessageCreditsAction::class)->execute($user->id, $llm->id);

    Event::assertNotDispatched(MessageCreditsLow::class);
});

it('does nothing and does not throw when the user has no active credit', function (): void {
    $user = User::factory()->workspace()->create();
    $llm = Llm::factory()->create(['credits' => 1]);

    app(ConsumeMessageCreditsAction::class)->execute($user->id, $llm->id);

    expect($user->fresh()->credit)->toBeNull();
});
