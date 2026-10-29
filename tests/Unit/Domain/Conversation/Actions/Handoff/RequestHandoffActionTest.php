<?php

use Domain\Agent\Models\Agent;
use Domain\Conversation\Actions\Handoff\RequestHandoffAction;
use Domain\Conversation\Enums\HandoffStatus;
use Domain\Conversation\Events\HandedOff;
use Domain\Conversation\Exceptions\HandoffUnavailableException;
use Domain\Conversation\Models\Conversation;
use Illuminate\Support\Facades\Event;

/**
 * A visitor asks for a person: an AI conversation goes to 'pending' and the
 * team hears it. The
 * agent's widget `handoff_enabled` switch (on by default) can turn live chat
 * off, and without a license key it is off for every agent.
 */
beforeEach(function (): void {
    Event::fake([HandedOff::class]);
    withLicense();
});

it('puts an AI conversation in the queue and broadcasts it', function (): void {
    $conversation = Conversation::factory()->create();

    $result = app(RequestHandoffAction::class)->execute($conversation);

    expect($result)->toBe(HandoffStatus::Pending)
        ->and($conversation->fresh()->handoff_status)->toBe(HandoffStatus::Pending);

    Event::assertDispatched(HandedOff::class, fn (HandedOff $event): bool => $event->conversationId === $conversation->external_id
        && $event->status === 'pending'
        && $event->adminId === null
        && $event->adminName === null);
});

it('refuses while the agent has live chat turned off', function (): void {
    $agent = Agent::factory()->create();
    $agent->setSetting('handoff_enabled', false, 'widget');
    $conversation = Conversation::factory()->forAgent($agent)->create();

    expect(fn () => app(RequestHandoffAction::class)->execute($conversation))
        ->toThrow(HandoffUnavailableException::class, 'Live chat is turned off for this agent.');

    expect($conversation->fresh()->handoff_status)->toBe(HandoffStatus::Ai);
    Event::assertNotDispatched(HandedOff::class);
});

it('refuses without a license key, even with live chat on', function (): void {
    withoutLicense();
    $conversation = Conversation::factory()->create();

    expect(fn () => app(RequestHandoffAction::class)->execute($conversation))
        ->toThrow(HandoffUnavailableException::class);

    expect($conversation->fresh()->handoff_status)->toBe(HandoffStatus::Ai);
    Event::assertNotDispatched(HandedOff::class);
});
