<?php

use Domain\Agent\Models\Agent;
use Domain\Conversation\Actions\Handoff\RequestHandoffAction;
use Domain\Conversation\Enums\ConversationSource;
use Domain\Conversation\Events\HandedOff;
use Domain\Conversation\Exceptions\HandoffOperationException;
use Domain\Conversation\Models\Conversation;
use Domain\Conversation\Models\Session;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;

/**
 * RequestHandoffAction routes a visitor from AI to a human teammate.
 * State machine: 'ai' → 'pending' → 'human' → 'returned' → 'ai'.
 * This action only ever pushes a conversation FROM 'ai' INTO 'pending'.
 *
 * Capability gate: the agent's team must have HANDOFF (Pro plan).
 * Per-agent toggle: `settings.widget.handoff_enabled` (default true).
 */
function makeHandoffScenario(string $initialStatus = 'ai', bool $proPlan = true, array $widgetSettings = []): Conversation
{
    if ($proPlan) {
        // Subscribe the owning workspace to the Pro plan so HANDOFF is granted.
        // We override the env-driven config price so the test owns the value.
        $priceId = 'price_test_pro_'.Str::random(6);
        config(['billing.packages.pro.stripe_monthly_price_id' => $priceId]);
    }

    $agent = Agent::factory()->create();

    if ($proPlan) {
        // Insert a Cashier-compatible subscription row pointing at the pro price.
        $agent->user->billing->subscriptions()->create([
            'type' => 'default',
            'stripe_id' => 'sub_test_'.Str::random(8),
            'stripe_status' => 'active',
            'stripe_price' => config('billing.packages.pro.stripe_monthly_price_id'),
            'quantity' => 1,
        ]);
        // Clear the per-request memo bucket so capabilities re-resolve after seeding.
        $agent->user->refresh();
    }

    foreach ($widgetSettings as $key => $value) {
        $agent->setSetting($key, $value, 'widget');
    }

    $session = Session::factory()->create([
        'agent_id' => $agent->id,
    ]);

    /** @var Conversation $conversation */
    $conversation = Conversation::factory()->create([
        'chat_session_id' => $session->id,
        'external_id' => (string) Str::uuid(),
        'source' => ConversationSource::Widget,
        'handoff_status' => $initialStatus,
    ]);

    return $conversation;
}

beforeEach(function (): void {
    Event::fake([HandedOff::class]);
});

it('transitions handoff_status from ai to pending and broadcasts HandedOff', function (): void {
    $conversation = makeHandoffScenario(initialStatus: 'ai');

    $result = app(RequestHandoffAction::class)->execute($conversation);

    expect($result)->toBe('pending')
        ->and($conversation->fresh()->handoff_status)->toBe('pending');

    Event::assertDispatched(HandedOff::class, function (HandedOff $event) use ($conversation): bool {
        return $event->conversationId === $conversation->external_id
            && $event->status === 'pending'
            && $event->adminId === null
            && $event->adminName === null;
    });
});

it('returns "human" without dispatching when the conversation is already with a human', function (): void {
    $conversation = makeHandoffScenario(initialStatus: 'human');

    $result = app(RequestHandoffAction::class)->execute($conversation);

    expect($result)->toBe('human')
        ->and($conversation->fresh()->handoff_status)->toBe('human');

    Event::assertNotDispatched(HandedOff::class);
});

it('returns "pending" without dispatching when handoff was already requested', function (): void {
    $conversation = makeHandoffScenario(initialStatus: 'pending');

    $result = app(RequestHandoffAction::class)->execute($conversation);

    expect($result)->toBe('pending')
        ->and($conversation->fresh()->handoff_status)->toBe('pending');

    Event::assertNotDispatched(HandedOff::class);
});

it('throws HandoffOperationException::forbidden when the plan does not include HANDOFF', function (): void {
    // No pro-plan subscription seeded → starter plan, no HANDOFF capability.
    $conversation = makeHandoffScenario(initialStatus: 'ai', proPlan: false);

    expect(fn () => app(RequestHandoffAction::class)->execute($conversation))
        ->toThrow(HandoffOperationException::class, 'Handoff is not available on this plan.');

    expect($conversation->fresh()->handoff_status)->toBe('ai');
    Event::assertNotDispatched(HandedOff::class);
});

it('throws HandoffOperationException::forbidden when the agent has handoff_enabled=false in widget settings', function (): void {
    // Pro plan grants capability, but the per-agent toggle is off.
    $conversation = makeHandoffScenario(
        initialStatus: 'ai',
        proPlan: true,
        widgetSettings: ['handoff_enabled' => false],
    );

    expect(fn () => app(RequestHandoffAction::class)->execute($conversation))
        ->toThrow(HandoffOperationException::class, 'Live chat is turned off for this agent.');

    expect($conversation->fresh()->handoff_status)->toBe('ai');
    Event::assertNotDispatched(HandedOff::class);
});
