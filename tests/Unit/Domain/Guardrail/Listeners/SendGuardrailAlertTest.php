<?php

use Domain\Agent\Models\Agent;
use Domain\Guardrail\Listeners\SendGuardrailAlert;
use Domain\Guardrail\Notifications\GuardrailAlertNotification;
use Illuminate\Support\Facades\Notification;
use Tests\Support\Guardrail\GuardrailTriggers;

/**
 * Blocked attacks and skipped checks reach the owner by email, when they
 * asked for it, at most once an hour for each kind; everyday findings such as
 * masked personal data never do.
 */
beforeEach(function (): void {
    Notification::fake();
    $this->agent = Agent::factory()->create();
});

function sgaFinding(Agent $agent, string $guardType, string $outcome, bool $alert = true): void
{
    $detail = $outcome === 'skipped'
        ? ['reason' => 'classifier_unavailable', 'failure' => 'unreachable', ...($alert ? ['alert' => true] : [])]
        : ['pattern' => 'ignore_instructions'];

    app(SendGuardrailAlert::class)->handle(GuardrailTriggers::event($agent, $guardType, $outcome, severity: 'high', detail: $detail));
}

it('emails the owner once an hour per kind about attacks and skipped checks, never about masked data', function (): void {
    $this->agent->setSetting('alert_owner', true, 'guardrails');

    sgaFinding($this->agent, 'prompt_injection', 'blocked');
    sgaFinding($this->agent, 'prompt_injection', 'blocked');
    sgaFinding($this->agent, 'toxicity', 'skipped');
    sgaFinding($this->agent, 'pii', 'redacted');

    Notification::assertSentToTimes($this->agent->workspace->owner, GuardrailAlertNotification::class, 2);
});
