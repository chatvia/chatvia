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

it('emails the owner once an hour about blocked attacks when alerts are on', function (): void {
    $this->agent->setSetting('alert_owner', true, 'guardrails');

    sgaFinding($this->agent, 'prompt_injection', 'blocked');
    sgaFinding($this->agent, 'prompt_injection', 'blocked');

    Notification::assertSentToTimes($this->agent->workspace->owner, GuardrailAlertNotification::class, 1);
});

it('emails about a skipped check separately from attacks', function (): void {
    $this->agent->setSetting('alert_owner', true, 'guardrails');

    sgaFinding($this->agent, 'prompt_injection', 'blocked');
    sgaFinding($this->agent, 'toxicity', 'skipped');

    Notification::assertSentToTimes($this->agent->workspace->owner, GuardrailAlertNotification::class, 2);
});

it('counts only the marked skip of a failed call as an alert', function (): void {
    $this->agent->setSetting('alert_owner', true, 'guardrails');

    sgaFinding($this->agent, 'blocked_topic', 'skipped', alert: false);

    Notification::assertNothingSent();
});

it('sends no email when alerts are off or the finding is not an attack', function (bool $alerts, string $guardType, string $outcome): void {
    $this->agent->setSetting('alert_owner', $alerts, 'guardrails');

    sgaFinding($this->agent, $guardType, $outcome);

    Notification::assertNothingSent();
})->with([
    'an attack while alerts are off' => [false, 'prompt_injection', 'blocked'],
    'masked personal data' => [true, 'pii', 'redacted'],
    'a finding that was only flagged' => [true, 'prompt_injection', 'flagged'],
]);
