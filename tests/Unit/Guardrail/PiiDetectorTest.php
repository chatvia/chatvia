<?php

use Domain\Guardrail\Services\Detectors\PiiDetector;

/**
 * PII redaction is the deterministic, free half of the guardrails — it runs on
 * every guarded turn, so a false negative leaks personal data and a false
 * positive mangles a normal answer. It touches nothing external; pure asserts.
 */
function pii(): PiiDetector
{
    return new PiiDetector;
}

it('redacts email addresses', function (): void {
    $r = pii()->redact('Reach me at john.doe@example.com please.');

    expect($r['text'])->toBe('Reach me at [redacted-email] please.')
        ->and($r['types'])->toContain('email')
        ->and($r['count'])->toBe(1);
});

it('redacts phone numbers', function (): void {
    $r = pii()->redact('Call 555-123-4567 today.');

    expect($r['text'])->toContain('[redacted-phone]')
        ->and($r['text'])->not->toContain('555-123-4567')
        ->and($r['types'])->toContain('phone');
});

it('redacts a Luhn-valid credit card', function (): void {
    $r = pii()->redact('My card is 4111 1111 1111 1111.');

    expect($r['text'])->toContain('[redacted-card]')
        ->and($r['types'])->toContain('card');
});

it('does NOT treat a non-Luhn 16-digit string as a card', function (): void {
    $r = pii()->redact('Order number 1234 5678 9012 3456 shipped.');

    expect($r['text'])->not->toContain('[redacted-card]');
});

it('redacts US SSNs', function (): void {
    $r = pii()->redact('SSN 123-45-6789 on file.');

    expect($r['text'])->toContain('[redacted-ssn]')
        ->and($r['types'])->toContain('ssn');
});

it('leaves clean text untouched', function (): void {
    $r = pii()->redact('What is your refund policy for orders over 30 days?');

    expect($r['count'])->toBe(0)
        ->and($r['types'])->toBe([])
        ->and($r['text'])->toBe('What is your refund policy for orders over 30 days?');
});

it('redacts several PII types in one message', function (): void {
    $r = pii()->redact('Email a@b.com or call 555-123-4567.');

    expect($r['types'])->toContain('email')
        ->and($r['types'])->toContain('phone')
        ->and($r['count'])->toBe(2);
});
