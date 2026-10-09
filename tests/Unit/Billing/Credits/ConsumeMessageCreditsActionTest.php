<?php

use App\Identity\Workspace\Models\User;
use Domain\Billing\Actions\Credits\ConsumeMessageCreditsAction;
use Domain\Billing\Contracts\Entitlements;
use Domain\Billing\Events\MessageCreditsLow;
use Domain\Billing\Jobs\UpdateUserCreditsJob;
use Domain\Billing\Models\UserCredit;
use Domain\Billing\Services\AutoRechargePolicy;
use Domain\Billing\Services\UserCreditSystem;
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

it('adds PDF page credits on top of the model cost', function (): void {
    $user = User::factory()->workspace()->create();
    $credit = activeCreditFor($user);
    $llm = Llm::factory()->create(['credits' => 1]);

    // A 10-page PDF turn: base 1 + 10 pages = 11 messages.
    app(ConsumeMessageCreditsAction::class)->execute($user->id, $llm->id, additionalMessages: 10);

    expect((int) $credit->usage()->value('messages'))->toBe(11);
});

it('charges only the base cost when there are no extra messages', function (): void {
    $user = User::factory()->workspace()->create();
    $credit = activeCreditFor($user);
    $llm = Llm::factory()->create(['credits' => 1]);

    app(ConsumeMessageCreditsAction::class)->execute($user->id, $llm->id);

    expect((int) $credit->usage()->value('messages'))->toBe(1);
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

it('charges a reply only once when the same charge key is processed again', function (): void {
    $user = User::factory()->workspace()->create();
    $credit = activeCreditFor($user);
    $llm = Llm::factory()->create(['credits' => 4]);

    app(ConsumeMessageCreditsAction::class)->execute($user->id, $llm->id, chargeKey: 'charge-1');
    app(ConsumeMessageCreditsAction::class)->execute($user->id, $llm->id, chargeKey: 'charge-1');

    expect((int) $credit->usage()->value('messages'))->toBe(4);

    // A different reply (different key) is charged normally.
    app(ConsumeMessageCreditsAction::class)->execute($user->id, $llm->id, chargeKey: 'charge-2');

    expect((int) $credit->usage()->value('messages'))->toBe(8);
});

it('does not charge twice when a retry follows a failure after the increment committed', function (): void {
    // The increment committed, then refreshing the send flag failed; the job
    // is retried with the same key. The retry must not increment again.
    $user = User::factory()->workspace()->create();
    $credit = activeCreditFor($user);
    $llm = Llm::factory()->create(['credits' => 4]);

    $failing = Mockery::mock(UserCreditSystem::class, [$user->id])->makePartial();
    $failing->shouldReceive('forWorkspace')->andReturnSelf();
    $failing->shouldReceive('refreshCanSendMessages')->once()->andThrow(new RuntimeException('cache unavailable'));

    expect(fn () => (new ConsumeMessageCreditsAction($failing, app(Entitlements::class), app(AutoRechargePolicy::class)))->execute($user->id, $llm->id, chargeKey: 'charge-retry'))
        ->toThrow(RuntimeException::class, 'cache unavailable');

    expect((int) $credit->usage()->value('messages'))->toBe(4);

    app(ConsumeMessageCreditsAction::class)->execute($user->id, $llm->id, chargeKey: 'charge-retry');

    expect((int) $credit->usage()->value('messages'))->toBe(4);
});

it('releases the charge key when the increment rolls back so the retry charges', function (): void {
    $user = User::factory()->workspace()->create();
    $credit = activeCreditFor($user);
    $llm = Llm::factory()->create(['credits' => 4]);

    // updateAfterCreditConsumption runs inside the charge transaction.
    $failing = Mockery::mock(UserCreditSystem::class, [$user->id])->makePartial();
    $failing->shouldReceive('forWorkspace')->andReturnSelf();
    $failing->shouldReceive('updateAfterCreditConsumption')->once()->andThrow(new RuntimeException('deadlock'));

    expect(fn () => (new ConsumeMessageCreditsAction($failing, app(Entitlements::class), app(AutoRechargePolicy::class)))->execute($user->id, $llm->id, chargeKey: 'charge-rollback'))
        ->toThrow(RuntimeException::class, 'deadlock');

    expect((int) $credit->usage()->value('messages'))->toBe(0);

    app(ConsumeMessageCreditsAction::class)->execute($user->id, $llm->id, chargeKey: 'charge-rollback');

    expect((int) $credit->usage()->value('messages'))->toBe(4);
});

it('gives every credits job its own charge key and reuses it when the job is retried', function (): void {
    $user = User::factory()->workspace()->create();
    $credit = activeCreditFor($user);
    $llm = Llm::factory()->create(['credits' => 1]);

    $job = new UpdateUserCreditsJob($user->id, $llm->id);
    $other = new UpdateUserCreditsJob($user->id, $llm->id);

    expect($job->chargeKey)->not->toBeNull()
        ->and($job->chargeKey)->not->toBe($other->chargeKey);

    // A retry runs the same serialised job again.
    $retried = unserialize(serialize($job));
    $job->handle(app(ConsumeMessageCreditsAction::class));
    $retried->handle(app(ConsumeMessageCreditsAction::class));

    expect((int) $credit->usage()->value('messages'))->toBe(1);

    // A job queued before the key existed must still unserialise: the property
    // needs a declared default, not a promoted constructor parameter.
    expect((new ReflectionProperty(UpdateUserCreditsJob::class, 'chargeKey'))->hasDefaultValue())->toBeTrue();
});
