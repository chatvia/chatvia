<?php

use Domain\Agent\Models\Agent;
use Domain\Guardrail\Events\GuardrailTriggered;
use Domain\Guardrail\Listeners\RecordGuardrailAttack;
use Domain\Guardrail\Listeners\SendGuardrailAlert;
use Domain\Guardrail\Notifications\GuardrailAlertNotification;
use Domain\Security\Events\SecurityEventOccurred;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;

/**
 * Blocked attacks and skipped checks reach the owner by email (when they
 * asked for it, at most once an hour) and attacks reach the security audit
 * log; everyday findings such as masked personal data do neither.
 */
function guardrailEvent(Agent $agent, string $guardType, string $outcome, string $stage = 'input'): GuardrailTriggered
{
    return new GuardrailTriggered(
        $agent->id, $agent->name, (int) $agent->workspace_id, null, null,
        'conv-1', 'msg-1', null, $stage, $guardType, $outcome, 'high', ['pattern' => 'ignore_instructions'], 'sample',
    );
}

beforeEach(function (): void {
    Cache::flush();
    Notification::fake();
    $this->agent = Agent::factory()->create();
});

it('emails the owner once an hour about blocked attacks when alerts are on', function (): void {
    $this->agent->setSetting('alert_owner', true, 'guardrails');
    $listener = app(SendGuardrailAlert::class);

    $listener->handle(guardrailEvent($this->agent, 'prompt_injection', 'blocked'));
    $listener->handle(guardrailEvent($this->agent, 'prompt_injection', 'blocked'));

    Notification::assertSentToTimes($this->agent->workspace->owner, GuardrailAlertNotification::class, 1);
});

it('sends no email when alerts are off or the finding is not an attack', function (): void {
    $listener = app(SendGuardrailAlert::class);
    $listener->handle(guardrailEvent($this->agent, 'prompt_injection', 'blocked'));

    $this->agent->setSetting('alert_owner', true, 'guardrails');
    $listener->handle(guardrailEvent($this->agent->fresh(), 'pii', 'redacted'));
    $listener->handle(guardrailEvent($this->agent->fresh(), 'prompt_injection', 'flagged'));

    Notification::assertNothingSent();
});

it('emails about a skipped check separately from attacks', function (): void {
    $this->agent->setSetting('alert_owner', true, 'guardrails');
    $listener = app(SendGuardrailAlert::class);

    $listener->handle(guardrailEvent($this->agent, 'prompt_injection', 'blocked'));
    $listener->handle(guardrailEvent($this->agent, 'toxicity', 'skipped'));

    Notification::assertSentToTimes($this->agent->workspace->owner, GuardrailAlertNotification::class, 2);
});

it('records a blocked attack in the security audit log, without the message', function (): void {
    Event::fake([SecurityEventOccurred::class]);

    app(RecordGuardrailAttack::class)->handle(guardrailEvent($this->agent, 'prompt_leak', 'blocked', 'output'));
    app(RecordGuardrailAttack::class)->handle(guardrailEvent($this->agent, 'pii', 'redacted'));

    Event::assertDispatchedTimes(SecurityEventOccurred::class, 1);
    Event::assertDispatched(SecurityEventOccurred::class, fn (SecurityEventOccurred $event): bool => $event->action === 'guardrail.attack_blocked'
        && $event->workspaceId === (int) $this->agent->workspace_id
        && ! str_contains($event->metadata, 'sample')
        && json_decode($event->metadata, true)['stage'] === 'output');
});
