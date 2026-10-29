<?php

use Domain\Guardrail\Services\Detectors\PiiAllowlist;
use Domain\Guardrail\Services\Detectors\PiiDetector;
use Illuminate\Support\Str;

/**
 * PII redaction is the deterministic, free half of the guardrails — it runs on
 * every guarded turn, so a false negative leaks personal data and a false
 * positive mangles a normal answer: order, invoice and tracking numbers,
 * dates, times and IP addresses reach the model and the order-lookup tools as
 * they are. Each value gets a numbered token per type, a repeat keeps its
 * token, and allowlisted values are never masked. It touches nothing
 * external; pure asserts.
 */
function pdDetector(): PiiDetector
{
    return new PiiDetector;
}

it('masks each kind of personal data and leaves its look-alikes', function (?array $filters, string $text, string $expected): void {
    $result = pdDetector()->redact($text, $filters);

    expect($result->text)->toBe($expected)
        ->and($result->count)->toBe(preg_match_all('/\[[A-Z_]+_\d+\]/', $expected));
})->with([
    'email' => [null, 'Reach me at john.doe@example.com please.', 'Reach me at [EMAIL_1] please.'],
    'Luhn-valid card, keeping the space after it' => [null, 'Card 4111 1111 1111 1111 exp 12/27', 'Card [CARD_1] exp 12/27'],
    'non-Luhn 16 digits' => [null, 'Order number 1234 5678 9012 3456 shipped.', 'Order number 1234 5678 9012 3456 shipped.'],
    'US SSN' => [null, 'SSN 123-45-6789 on file.', 'SSN [US_SSN_1] on file.'],
    'German social-insurance number' => [null, 'My Versicherungsnummer is 15070649C103.', 'My Versicherungsnummer is [GERMAN_SSN_1].'],
    'Danish CPR with a dash' => [null, 'CPR 070761-4285.', 'CPR [DANISH_CPR_1].'],
    'Danish CPR without a dash' => [null, 'CPR 0707614285.', 'CPR [DANISH_CPR_1].'],
    'Danish CPR with an invalid date' => [['danish_cpr'], 'Reference 5512994285.', 'Reference 5512994285.'],
    'IBAN by mod-97' => [['iban'], 'Pay to DE89 3704 0044 0532 0130 00 or NL91ABNA0417164300, not AB12 CDEF GHIJ KLMN.', 'Pay to [IBAN_1] or [IBAN_2], not AB12 CDEF GHIJ KLMN.'],
    'IP address, not a version or a time' => [['ip_address'], 'From 192.168.1.20 and 2001:db8::ff00:42:8329, version 10.0.19045.3803 at 12:30:45.', 'From [IP_1] and [IP_2], version 10.0.19045.3803 at 12:30:45.'],
    'UK NINO with a valid prefix only' => [['uk_nino'], 'NINO AB123456D and GB123456A.', 'NINO [UK_NINO_1] and GB123456A.'],
    'provider API keys and a private key' => [['api_key'], 'Keys: sk-or-v1-'.Str::repeat('a', 64).', ghp_'.Str::repeat('A', 36)." and AKIAIOSFODNN7EXAMPLE.\n-----BEGIN RSA PRIVATE KEY-----\nAAAA\n-----END RSA PRIVATE KEY-----", "Keys: [API_KEY_1], [API_KEY_2] and [API_KEY_3].\n[API_KEY_4]"],
    'a plain hash or id is no API key' => [['api_key'], 'Commit 9fceb02d0ae598e95dc970b74767f19372d61af8, order sk-12345.', 'Commit 9fceb02d0ae598e95dc970b74767f19372d61af8, order sk-12345.'],
    'phone, international with +' => [['phone'], 'Call me on +44 20 7946 0958 please.', 'Call me on [PHONE_1] please.'],
    'phone, international with 00' => [['phone'], 'Call me on 0049 30 1234567 please.', 'Call me on [PHONE_1] please.'],
    'phone, national with a slash' => [['phone'], 'Call me on 030/1234567 please.', 'Call me on [PHONE_1] please.'],
    'phone, national in brackets' => [['phone'], 'Call me on (030) 123 4567 please.', 'Call me on [PHONE_1] please.'],
    'phone, North American with separators' => [['phone'], 'Call me on 1-800-555-1234 please.', 'Call me on [PHONE_1] please.'],
    'phone, North American in brackets' => [['phone'], 'Call me on (800) 555-1234 please.', 'Call me on [PHONE_1] please.'],
    'phone, North American digits only' => [['phone'], 'Call me on 8005551234 please.', 'Call me on [PHONE_1] please.'],
    'order number with #' => [null, 'Your order #20241015123 shipped.', 'Your order #20241015123 shipped.'],
    'order number without #' => [null, 'Order 20241015123 is on its way.', 'Order 20241015123 is on its way.'],
    'date and time' => [null, 'It shipped on 2024-10-15 12:30.', 'It shipped on 2024-10-15 12:30.'],
    'day-first date and time' => [null, 'Booked for 15.10.2024 10:30.', 'Booked for 15.10.2024 10:30.'],
    'IPv4 address with the default filters' => [null, 'The server is at 192.168.100.200.', 'The server is at 192.168.100.200.'],
    'decimal' => [null, 'The rate is 0.123456789.', 'The rate is 0.123456789.'],
    'URL path' => [null, 'Open https://acme.com/orders/0123456789 to track it.', 'Open https://acme.com/orders/0123456789 to track it.'],
    'an empty filter list' => [[], 'Email a@b.com, SSN 123-45-6789.', 'Email a@b.com, SSN 123-45-6789.'],
]);

it('numbers distinct values per type and reuses the token for repeats', function (): void {
    $result = pdDetector()->redact('Mail a@b.com or c@d.com, then resend to a@b.com; card 4111 1111 1111 1111, SSN 123-45-6789.');

    expect($result->text)->toBe('Mail [EMAIL_1] or [EMAIL_2], then resend to [EMAIL_1]; card [CARD_1], SSN [US_SSN_1].')
        ->and($result->types)->toContain('email', 'credit_card', 'us_ssn')
        ->and($result->count)->toBe(5);
});

it('never masks allowlisted values', function (): void {
    $allowlist = new PiiAllowlist(['support@acme.com', '@help.acme.com', '+49 30 1234567']);

    $result = pdDetector()->redact(
        'support@acme.com, sales@help.acme.com, jo@gmail.com, 030 1234567, 030 7654321',
        null,
        $allowlist,
    );

    expect($result->text)->toBe('support@acme.com, sales@help.acme.com, [EMAIL_1], 030 1234567, [PHONE_1]');
});
