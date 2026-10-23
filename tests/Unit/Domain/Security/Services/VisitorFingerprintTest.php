<?php

use Domain\Security\Services\VisitorFingerprint;

/**
 * A visitor's fingerprint is a stable keyed hash of their network and browser,
 * different in every workspace, counting an IPv6 address by its /64, and
 * missing when there is no usable address.
 */
beforeEach(function (): void {
    $this->fingerprint = new VisitorFingerprint;
});

it('gives the same visitor the same key, and another browser or network another one', function (): void {
    $key = $this->fingerprint->for(1, '203.0.113.7', 'Firefox/130');

    expect($key)->toMatch('/^[0-9a-f]{64}$/')
        ->and($this->fingerprint->for(1, '203.0.113.7', 'Firefox/130'))->toBe($key)
        ->and($this->fingerprint->for(1, '203.0.113.7', 'Chrome/129'))->not->toBe($key)
        ->and($this->fingerprint->for(1, '203.0.113.8', 'Firefox/130'))->not->toBe($key);
});

it('gives the same visitor unrelated keys in two workspaces', function (): void {
    expect($this->fingerprint->for(1, '203.0.113.7', 'Firefox/130'))
        ->not->toBe($this->fingerprint->for(2, '203.0.113.7', 'Firefox/130'));
});

it('counts an IPv6 address by its /64 network', function (): void {
    expect($this->fingerprint->for(1, '2001:db8:1:2:aaaa::1', 'Safari'))
        ->toBe($this->fingerprint->for(1, '2001:db8:1:2:bbbb:cccc::9', 'Safari'))
        ->not->toBe($this->fingerprint->for(1, '2001:db8:1:3::1', 'Safari'));
});

it('has no key without a usable address', function (?string $ip): void {
    expect($this->fingerprint->for(1, $ip, 'Firefox/130'))->toBeNull();
})->with([null, '', 'not-an-ip']);
