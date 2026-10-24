<?php

use Domain\Agent\Models\Agent;
use Domain\Guardrail\Listeners\RecordGuardrailAttack;
use Domain\Security\Actions\RecordSecurityEventAction;
use Domain\Security\Enums\SecurityAuditAction;
use Domain\Security\Enums\SecurityAuditOutcome;
use Domain\Security\Events\SecurityEventOccurred;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Tests\Support\Guardrail\GuardrailTriggers;

/**
 * A blocked attack reaches the security audit log, without the visitor's
 * message; everyday findings such as masked personal data do not.
 */
beforeEach(function (): void {
    Event::fake([SecurityEventOccurred::class]);
    $this->agent = Agent::factory()->create();
});

it('records a blocked attack in the security audit log, without the message', function (): void {
    app(RecordGuardrailAttack::class)->handle(GuardrailTriggers::event($this->agent, 'prompt_leak', 'blocked', 'output', severity: 'high'));
    app(RecordGuardrailAttack::class)->handle(GuardrailTriggers::event($this->agent, 'pii', 'redacted'));

    Event::assertDispatchedTimes(SecurityEventOccurred::class, 1);
    Event::assertDispatched(SecurityEventOccurred::class, fn (SecurityEventOccurred $event): bool => $event->action === 'guardrail.attack_blocked'
        && $event->workspaceId === (int) $this->agent->workspace_id
        && ! Str::contains($event->metadata, 'sample')
        && json_decode($event->metadata, true)['stage'] === 'output');
});

it('writes a blocked attack to the audit log table with an outcome the table allows', function (): void {
    app(RecordGuardrailAttack::class)->handle(GuardrailTriggers::event($this->agent, 'prompt_injection', 'blocked', severity: 'high'));

    $recorded = Event::dispatched(SecurityEventOccurred::class)->first()[0];
    $row = app(RecordSecurityEventAction::class)->execute($recorded);

    expect($row->fresh())
        ->action->toBe(SecurityAuditAction::GuardrailAttackBlocked)
        ->outcome->toBe(SecurityAuditOutcome::Denied)
        ->target_id->toBe((string) $this->agent->id);
});
