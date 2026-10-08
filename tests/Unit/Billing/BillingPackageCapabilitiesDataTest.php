<?php

use Domain\Billing\Data\Resources\Package\BillingPackageCapabilitiesData as Capabilities;

/**
 * Pins the security tiering: the `security` suite (network access, retention,
 * audit viewer) and the standalone `siemWebhook` capability both belong to Pro
 * (the `pro` key) and Enterprise (= Pro + manual grants), and nothing below.
 */
it('grants the SIEM webhook capability to Pro and Enterprise only', function (): void {
    expect(Capabilities::forPlan('pro')->siemWebhook)->toBeTrue()
        ->and(Capabilities::forPlan('enterprise')->siemWebhook)->toBeTrue()
        ->and(Capabilities::forPlan('standard')->siemWebhook)->toBeFalse()
        ->and(Capabilities::forPlan('starter')->siemWebhook)->toBeFalse();
});

it('keeps the security suite on Pro and Enterprise only', function (): void {
    expect(Capabilities::forPlan('pro')->security)->toBeTrue()
        ->and(Capabilities::forPlan('enterprise')->security)->toBeTrue()
        ->and(Capabilities::forPlan('standard')->security)->toBeFalse()
        ->and(Capabilities::forPlan('starter')->security)->toBeFalse();
});

it('round-trips the SIEM capability through fromArray', function (): void {
    expect(Capabilities::fromArray(['siemWebhook' => true])->siemWebhook)->toBeTrue()
        ->and(Capabilities::fromArray([])->siemWebhook)->toBeFalse();
});

/**
 * Pins the agent-governance tiering: Eval suites and Guardrails ship from
 * Standard up; the per-agent tool-call audit log is Pro (and Enterprise) only.
 */
it('grants Evals and Guardrails to Standard, Pro and Enterprise — never Starter', function (): void {
    expect(Capabilities::forPlan('starter')->evals)->toBeFalse()
        ->and(Capabilities::forPlan('starter')->guardrails)->toBeFalse()
        ->and(Capabilities::forPlan('standard')->evals)->toBeTrue()
        ->and(Capabilities::forPlan('standard')->guardrails)->toBeTrue()
        ->and(Capabilities::forPlan('pro')->evals)->toBeTrue()
        ->and(Capabilities::forPlan('pro')->guardrails)->toBeTrue()
        ->and(Capabilities::forPlan('enterprise')->evals)->toBeTrue()
        ->and(Capabilities::forPlan('enterprise')->guardrails)->toBeTrue();
});

it('keeps the audit log on Pro and Enterprise only', function (): void {
    expect(Capabilities::forPlan('pro')->audit)->toBeTrue()
        ->and(Capabilities::forPlan('enterprise')->audit)->toBeTrue()
        ->and(Capabilities::forPlan('standard')->audit)->toBeFalse()
        ->and(Capabilities::forPlan('starter')->audit)->toBeFalse();
});

it('round-trips the governance capabilities through fromArray', function (): void {
    expect(Capabilities::fromArray(['evals' => true])->evals)->toBeTrue()
        ->and(Capabilities::fromArray(['guardrails' => true])->guardrails)->toBeTrue()
        ->and(Capabilities::fromArray(['audit' => true])->audit)->toBeTrue()
        ->and(Capabilities::fromArray([])->evals)->toBeFalse()
        ->and(Capabilities::fromArray([])->guardrails)->toBeFalse()
        ->and(Capabilities::fromArray([])->audit)->toBeFalse();
});

/**
 * The plan table lives in config/billing.php. Pin every plan's full feature
 * set so a config edit can't silently change what a paying plan includes.
 */
it('reads each plan from config with the same features as before', function (): void {
    $off = [
        'sentiment' => false, 'tone' => false, 'topics' => false, 'handoff' => false,
        'api' => false, 'pulse' => false, 'evals' => false, 'guardrails' => false,
        'audit' => false, 'removeBranding' => false, 'security' => false, 'siemWebhook' => false,
    ];
    $all = array_map(fn (): bool => true, $off);

    expect(Capabilities::forPlan('starter')->toArray())->toBe([...$off, 'retentionDays' => 90])
        ->and(Capabilities::forPlan('standard')->toArray())->toBe([
            ...$off,
            'sentiment' => true, 'handoff' => true, 'api' => true, 'evals' => true, 'guardrails' => true,
            'retentionDays' => 180,
        ])
        ->and(Capabilities::forPlan('pro')->toArray())->toBe([...$all, 'retentionDays' => 366])
        ->and(Capabilities::forPlan('enterprise')->toArray())->toBe([...$all, 'retentionDays' => 366]);
});

it('matches the plan key without regard to case', function (): void {
    expect(Capabilities::forPlan('PRO')->audit)->toBeTrue();
});

it('gives an unknown plan every feature off and the default retention', function (): void {
    $capabilities = Capabilities::forPlan('no-such-plan');

    expect($capabilities->api)->toBeFalse()
        ->and($capabilities->evals)->toBeFalse()
        ->and($capabilities->retentionDays)->toBe(Capabilities::DEFAULT_RETENTION_DAYS);
});

it('turns every feature on for the unlimited set', function (): void {
    $capabilities = Capabilities::unlimited(400)->toArray();

    expect($capabilities['retentionDays'])->toBe(400);

    unset($capabilities['retentionDays']);

    expect(array_unique(array_values($capabilities)))->toBe([true]);
});
