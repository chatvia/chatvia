<?php

use Domain\Billing\Data\Resources\Package\BillingPackageCapabilitiesData as Capabilities;

/**
 * Pins the security tiering: the `security` suite (network access, retention,
 * audit viewer) and the standalone `siemWebhook` capability both belong to Pro
 * (the `pro` key) and Enterprise (= Pro + manual grants), and nothing below.
 */
it('grants the SIEM webhook capability to Pro and Enterprise only', function (): void {
    expect(Capabilities::pro()->siemWebhook)->toBeTrue()
        ->and(Capabilities::enterprise()->siemWebhook)->toBeTrue()
        ->and(Capabilities::standard()->siemWebhook)->toBeFalse()
        ->and(Capabilities::starter()->siemWebhook)->toBeFalse();
});

it('keeps the security suite on Pro and Enterprise only', function (): void {
    expect(Capabilities::pro()->security)->toBeTrue()
        ->and(Capabilities::enterprise()->security)->toBeTrue()
        ->and(Capabilities::standard()->security)->toBeFalse()
        ->and(Capabilities::starter()->security)->toBeFalse();
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
    expect(Capabilities::starter()->evals)->toBeFalse()
        ->and(Capabilities::starter()->guardrails)->toBeFalse()
        ->and(Capabilities::standard()->evals)->toBeTrue()
        ->and(Capabilities::standard()->guardrails)->toBeTrue()
        ->and(Capabilities::pro()->evals)->toBeTrue()
        ->and(Capabilities::pro()->guardrails)->toBeTrue()
        ->and(Capabilities::enterprise()->evals)->toBeTrue()
        ->and(Capabilities::enterprise()->guardrails)->toBeTrue();
});

it('keeps the audit log on Pro and Enterprise only', function (): void {
    expect(Capabilities::pro()->audit)->toBeTrue()
        ->and(Capabilities::enterprise()->audit)->toBeTrue()
        ->and(Capabilities::standard()->audit)->toBeFalse()
        ->and(Capabilities::starter()->audit)->toBeFalse();
});

it('round-trips the governance capabilities through fromArray', function (): void {
    expect(Capabilities::fromArray(['evals' => true])->evals)->toBeTrue()
        ->and(Capabilities::fromArray(['guardrails' => true])->guardrails)->toBeTrue()
        ->and(Capabilities::fromArray(['audit' => true])->audit)->toBeTrue()
        ->and(Capabilities::fromArray([])->evals)->toBeFalse()
        ->and(Capabilities::fromArray([])->guardrails)->toBeFalse()
        ->and(Capabilities::fromArray([])->audit)->toBeFalse();
});
