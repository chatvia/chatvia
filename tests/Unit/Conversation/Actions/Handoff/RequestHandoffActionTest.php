<?php

use Domain\Agent\Models\Agent;
use Domain\Conversation\Actions\Handoff\RequestHandoffAction;
use Domain\Conversation\Enums\ConversationSource;
use Domain\Conversation\Enums\HandoffStatus;
use Domain\Conversation\Events\HandedOff;
use Domain\Conversation\Exceptions\HandoffUnavailableException;
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
    // proPlanAgent() (tests/Pest.php) provisions billing + an active Pro
    // subscription explicitly — factory users carry no billing rows anymore,
    // so the HANDOFF capability must be seeded through the shared helper.
    $agent = $proPlan ? proPlanAgent() : Agent::factory()->create();

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

    expect($result)->toBe(HandoffStatus::Pending)
        ->and($conversation->fresh()->handoff_status)->toBe(HandoffStatus::Pending);

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

    expect($result)->toBe(HandoffStatus::Human)
        ->and($conversation->fresh()->handoff_status)->toBe(HandoffStatus::Human);

    Event::assertNotDispatched(HandedOff::class);
});

it('returns "pending" without dispatching when handoff was already requested', function (): void {
    $conversation = makeHandoffScenario(initialStatus: 'pending');

    $result = app(RequestHandoffAction::class)->execute($conversation);

    expect($result)->toBe(HandoffStatus::Pending)
        ->and($conversation->fresh()->handoff_status)->toBe(HandoffStatus::Pending);

    Event::assertNotDispatched(HandedOff::class);
});

it('refuses with HandoffUnavailableException when the plan does not include HANDOFF', function (): void {
    // No pro-plan subscription seeded → starter plan, no HANDOFF capability.
    $conversation = makeHandoffScenario(initialStatus: 'ai', proPlan: false);

    expect(fn () => app(RequestHandoffAction::class)->execute($conversation))
        ->toThrow(HandoffUnavailableException::class, 'Handoff is not available on this plan.');

    expect($conversation->fresh()->handoff_status)->toBe(HandoffStatus::Ai);
    Event::assertNotDispatched(HandedOff::class);
});

it('refuses with HandoffUnavailableException when the agent has handoff_enabled=false in widget settings', function (): void {
    // Pro plan grants capability, but the per-agent toggle is off.
    $conversation = makeHandoffScenario(
        initialStatus: 'ai',
        proPlan: true,
        widgetSettings: ['handoff_enabled' => false],
    );

    expect(fn () => app(RequestHandoffAction::class)->execute($conversation))
        ->toThrow(HandoffUnavailableException::class, 'Live chat is turned off for this agent.');

    expect($conversation->fresh()->handoff_status)->toBe(HandoffStatus::Ai);
    Event::assertNotDispatched(HandedOff::class);
});
