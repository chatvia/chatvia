<?php

use Support\License\LicenseFeature;
use Support\License\LicenseKey;
use Support\License\LicenseSignature;
use Tests\Support\LicenseKeys;

/**
 * License keys are Ed25519-signed terms, checked offline against the public
 * key the app ships with. Anything else, or a key with a changed part, reads
 * as no key at all.
 */
it('reads the key the chatvia.ai site signs', function (): void {
    $license = LicenseSignature::verify(LicenseKeys::BOUGHT_KEY, LicenseKeys::PUBLIC_KEY);

    expect($license)->toBeInstanceOf(LicenseKey::class)
        ->and($license->id)->toBe(LicenseKeys::ID)
        ->and($license->licensee)->toBe('Acme Ltd')
        ->and($license->domains)->toBe([])
        ->and($license->allows(LicenseFeature::HideCredit))->toBeTrue()
        ->and($license->issued)->toBe('2026-10-15');
});

it('signs the same text the chatvia.ai site does', function (): void {
    expect(LicenseKeys::activatedFor())->toBe(LicenseKeys::BOUGHT_KEY);
});

it('reads a key that wrapped when it was pasted', function (): void {
    $wrapped = chunk_split(LicenseKeys::activatedFor('chat.acme.com'), 40, "\n  ");

    expect(LicenseSignature::verify($wrapped, LicenseKeys::PUBLIC_KEY)?->domains)->toBe(['chat.acme.com']);
});

it('reads nothing from a changed or foreign key', function (string $key, ?string $publicKey): void {
    expect(LicenseSignature::verify($key, $publicKey))->toBeNull();
})->with([
    'terms changed' => fn (): array => [substr_replace(LicenseKeys::BOUGHT_KEY, LicenseKeys::BOUGHT_KEY[20] === 'A' ? 'B' : 'A', 20, 1), LicenseKeys::PUBLIC_KEY],
    'another version' => fn (): array => ['cvl2'.substr(LicenseKeys::BOUGHT_KEY, 4), LicenseKeys::PUBLIC_KEY],
    'another public key' => fn (): array => [LicenseKeys::BOUGHT_KEY, base64_encode(sodium_crypto_sign_publickey(sodium_crypto_sign_keypair()))],
    'no public key' => fn (): array => [LicenseKeys::BOUGHT_KEY, null],
    'a public key of the wrong size' => fn (): array => [LicenseKeys::BOUGHT_KEY, base64_encode('short')],
    'empty' => ['', LicenseKeys::PUBLIC_KEY],
    'not a key' => ['hello world', LicenseKeys::PUBLIC_KEY],
    'empty parts' => ['cvl1..', LicenseKeys::PUBLIC_KEY],
    'no signature' => fn (): array => [substr(LicenseKeys::BOUGHT_KEY, 0, (int) strrpos(LicenseKeys::BOUGHT_KEY, '.')), LicenseKeys::PUBLIC_KEY],
]);

it('leaves out a feature this version does not know', function (): void {
    $terms = 'cvl1.'.rtrim(strtr(base64_encode((string) json_encode([
        'id' => 'x', 'licensee' => 'Acme Ltd', 'domains' => [], 'features' => ['hide_credit', 'something_new'], 'issued' => '2026-10-15',
    ])), '+/', '-_'), '=');
    $signature = sodium_crypto_sign_detached($terms, base64_decode(LicenseKeys::SECRET_KEY));
    $key = $terms.'.'.rtrim(strtr(base64_encode($signature), '+/', '-_'), '=');

    expect(LicenseSignature::verify($key, LicenseKeys::PUBLIC_KEY)?->features)->toBe([LicenseFeature::HideCredit]);
});

it('matches a host whatever its case, port or trailing dot', function (string $host, bool $covered): void {
    $license = LicenseSignature::verify(LicenseKeys::activatedFor('chat.acme.com'), LicenseKeys::PUBLIC_KEY);

    expect($license?->coversHost($host))->toBe($covered);
})->with([
    ['chat.acme.com', true],
    ['CHAT.Acme.com', true],
    ['chat.acme.com:8443', true],
    ['chat.acme.com.', true],
    ['acme.com', false],
    ['evil-chat.acme.com', false],
    ['', false],
]);
