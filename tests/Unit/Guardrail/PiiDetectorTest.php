<?php

use Domain\Guardrail\Services\Detectors\PiiAllowlist;
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

    expect($r->text)->toBe('Reach me at [EMAIL_1] please.')
        ->and($r->types)->toContain('email')
        ->and($r->count)->toBe(1);
});

it('redacts phone numbers', function (): void {
    $r = pii()->redact('Call 555-123-4567 today.');

    expect($r->text)->toContain('[PHONE_1]')
        ->and($r->text)->not->toContain('555-123-4567')
        ->and($r->types)->toContain('phone');
});

it('redacts a Luhn-valid credit card', function (): void {
    $r = pii()->redact('My card is 4111 1111 1111 1111.');

    expect($r->text)->toContain('[CARD_1]')
        ->and($r->types)->toContain('credit_card');
});

it('does NOT treat a non-Luhn 16-digit string as a card', function (): void {
    $r = pii()->redact('Order number 1234 5678 9012 3456 shipped.');

    expect($r->text)->not->toContain('[CARD_1]');
});

it('redacts US SSNs', function (): void {
    $r = pii()->redact('SSN 123-45-6789 on file.');

    expect($r->text)->toContain('[US_SSN_1]')
        ->and($r->types)->toContain('us_ssn');
});

it('redacts a German social-insurance number', function (): void {
    $r = pii()->redact('My Versicherungsnummer is 15070649C103.');

    expect($r->text)->toContain('[GERMAN_SSN_1]')
        ->and($r->types)->toContain('german_ssn');
});

it('redacts a Danish CPR number with and without the dash', function (): void {
    expect(pii()->redact('CPR 070761-4285.')->text)->toContain('[DANISH_CPR_1]')
        ->and(pii()->redact('CPR 0707614285.')->text)->toContain('[DANISH_CPR_1]');
});

it('does NOT treat an invalid-date 10-digit string as a Danish CPR', function (): void {
    // 55 is not a valid day → the date guard rejects it (would otherwise look like CPR).
    $r = pii()->redact('Reference 5512994285.', ['danish_cpr']);

    expect($r->text)->not->toContain('[DANISH_CPR_1]')
        ->and($r->count)->toBe(0);
});

it('numbers distinct values per type and reuses the token for repeats', function (): void {
    // Two distinct emails get distinct numbers; the first one repeats and keeps
    // its number — coreference survives, and the model can tell them apart.
    $r = pii()->redact('Mail a@b.com or c@d.com, then resend to a@b.com.');

    expect($r->text)->toBe('Mail [EMAIL_1] or [EMAIL_2], then resend to [EMAIL_1].')
        ->and($r->count)->toBe(3);
});

it('numbers each PII type independently', function (): void {
    $r = pii()->redact('Email a@b.com, card 4111 1111 1111 1111, SSN 123-45-6789.');

    expect($r->text)->toContain('[EMAIL_1]')
        ->and($r->text)->toContain('[CARD_1]')
        ->and($r->text)->toContain('[US_SSN_1]');
});

it('only runs the filters it is given', function (): void {
    // Email selected, US SSN not — the SSN must survive untouched.
    $r = pii()->redact('Email a@b.com, SSN 123-45-6789.', ['email']);

    expect($r->text)->toBe('Email [EMAIL_1], SSN 123-45-6789.')
        ->and($r->types)->toBe(['email'])
        ->and($r->count)->toBe(1);
});

it('masks nothing when given an empty filter list', function (): void {
    $r = pii()->redact('Email a@b.com, SSN 123-45-6789.', []);

    expect($r->text)->toBe('Email a@b.com, SSN 123-45-6789.')
        ->and($r->count)->toBe(0);
});

it('leaves clean text untouched', function (): void {
    $r = pii()->redact('What is your refund policy for orders over 30 days?');

    expect($r->count)->toBe(0)
        ->and($r->types)->toBe([])
        ->and($r->text)->toBe('What is your refund policy for orders over 30 days?');
});

it('redacts several PII types in one message', function (): void {
    $r = pii()->redact('Email a@b.com or call 555-123-4567.');

    expect($r->types)->toContain('email')
        ->and($r->types)->toContain('phone')
        ->and($r->count)->toBe(2);
});

it('keeps order, invoice and tracking numbers, dates, times and IP addresses', function (string $text): void {
    // These reach the model and the order-lookup tools as they are.
    expect(pii()->redact($text)->count)->toBe(0);
})->with([
    'order with #' => ['Your order #20241015123 shipped.'],
    'order without #' => ['Order 20241015123 is on its way.'],
    'date and time' => ['It shipped on 2024-10-15 12:30.'],
    'day-first date and time' => ['Booked for 15.10.2024 10:30.'],
    'invoice' => ['See invoice 4000-1234-5678.'],
    'passport-style number' => ['Reference 533380006.'],
    'IP address' => ['The server is at 192.168.100.200.'],
    'decimal' => ['The rate is 0.123456789.'],
    'URL path' => ['Open https://acme.com/orders/0123456789 to track it.'],
]);

it('masks phone numbers in common international and national formats', function (string $phone): void {
    $result = pii()->redact("Call me on {$phone} please.", ['phone']);

    expect($result->text)->toBe('Call me on [PHONE_1] please.');
})->with([
    '+1 800 555 1234',
    '+1 (800) 555-1234',
    '1-800-555-1234',
    '(800) 555-1234',
    '8005551234',
    '0049 30 1234567',
    '030/1234567',
    '(030) 123 4567',
    '0151 12345678',
    '+44 20 7946 0958',
    '+45 30 12 34 56',
]);

it('keeps the space after a masked card number', function (): void {
    expect(pii()->redact('Card 4111 1111 1111 1111 exp 12/27')->text)->toBe('Card [CARD_1] exp 12/27');
});

it('never masks allowlisted values', function (): void {
    $allowlist = new PiiAllowlist(['support@acme.com', '@help.acme.com', '+49 30 1234567']);

    $result = pii()->redact(
        'support@acme.com, sales@help.acme.com, jo@gmail.com, 030 1234567, 030 7654321',
        null,
        $allowlist,
    );

    expect($result->text)->toBe('support@acme.com, sales@help.acme.com, [EMAIL_1], 030 1234567, [PHONE_1]');
});

it('masks IBANs that pass the mod-97 check, with or without spaces', function (): void {
    $result = pii()->redact('Pay to DE89 3704 0044 0532 0130 00 or NL91ABNA0417164300, not AB12 CDEF GHIJ KLMN.', ['iban']);

    expect($result->text)->toBe('Pay to [IBAN_1] or [IBAN_2], not AB12 CDEF GHIJ KLMN.');
});

it('masks IP addresses but not version numbers or times', function (): void {
    $result = pii()->redact('From 192.168.1.20 and 2001:db8::ff00:42:8329, version 10.0.19045.3803 at 12:30:45.', ['ip_address']);

    expect($result->text)->toBe('From [IP_1] and [IP_2], version 10.0.19045.3803 at 12:30:45.');
});

it('masks a UK National Insurance number with a valid prefix only', function (): void {
    expect(pii()->redact('NINO AB123456D and GB123456A.', ['uk_nino'])->text)->toBe('NINO [UK_NINO_1] and GB123456A.');
});
