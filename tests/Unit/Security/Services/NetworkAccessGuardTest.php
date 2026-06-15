<?php

use Domain\Security\Services\NetworkAccessGuard;

/**
 * Pure decision logic for the workspace network gate — IP allowlist (fails
 * closed) combined with geo allow/deny (fails OPEN when the country is
 * unresolved, so a geo-IP outage never locks a workspace out of its dashboard).
 */
beforeEach(function (): void {
    $this->guard = new NetworkAccessGuard;
});

it('allows any IP when the allowlist is empty', function (): void {
    expect($this->guard->passes([], 'off', [], '203.0.113.9', null))->toBeTrue();
});

it('allows an IP inside an allowlisted CIDR', function (): void {
    expect($this->guard->passes(['203.0.113.0/24'], 'off', [], '203.0.113.9', null))->toBeTrue();
});

it('allows an exact allowlisted IP', function (): void {
    expect($this->guard->passes(['198.51.100.7'], 'off', [], '198.51.100.7', null))->toBeTrue();
});

it('blocks an IP outside the allowlist', function (): void {
    expect($this->guard->passes(['203.0.113.0/24'], 'off', [], '198.51.100.7', null))->toBeFalse();
});

it('permits a country on the allow list', function (): void {
    expect($this->guard->passes([], 'allow', ['AE', 'SA'], '203.0.113.9', 'AE'))->toBeTrue();
});

it('blocks a country not on the allow list', function (): void {
    expect($this->guard->passes([], 'allow', ['AE', 'SA'], '203.0.113.9', 'US'))->toBeFalse();
});

it('blocks a country on the deny list', function (): void {
    expect($this->guard->passes([], 'deny', ['RU'], '203.0.113.9', 'RU'))->toBeFalse();
});

it('permits a country not on the deny list', function (): void {
    expect($this->guard->passes([], 'deny', ['RU'], '203.0.113.9', 'US'))->toBeTrue();
});

it('matches countries case-insensitively', function (): void {
    // The geo-IP provider and the countries table both yield lowercase codes,
    // but stored values may arrive in any case — comparison must not care.
    expect($this->guard->passes([], 'allow', ['ae'], '203.0.113.9', 'AE'))->toBeTrue()
        ->and($this->guard->passes([], 'deny', ['RU'], '203.0.113.9', 'ru'))->toBeFalse();
});

it('fails open on geo when the country is unresolved', function (): void {
    expect($this->guard->passes([], 'allow', ['AE'], '203.0.113.9', null))->toBeTrue()
        ->and($this->guard->passes([], 'deny', ['RU'], '203.0.113.9', null))->toBeTrue();
});

it('requires both the IP allowlist and the geo rule to pass', function (): void {
    expect($this->guard->passes(['203.0.113.0/24'], 'allow', ['AE'], '203.0.113.9', 'US'))->toBeFalse() // allowed IP, blocked country
        ->and($this->guard->passes(['203.0.113.0/24'], 'allow', ['US'], '198.51.100.7', 'US'))->toBeFalse() // blocked IP, allowed country
        ->and($this->guard->passes(['203.0.113.0/24'], 'allow', ['US'], '203.0.113.9', 'US'))->toBeTrue(); // both pass
});

it('judges the IP allowlist in isolation', function (): void {
    expect($this->guard->ipAllowed([], '198.51.100.7'))->toBeTrue() // empty ⇒ no restriction
        ->and($this->guard->ipAllowed(['203.0.113.0/24'], '203.0.113.9'))->toBeTrue()
        ->and($this->guard->ipAllowed(['203.0.113.0/24'], '198.51.100.7'))->toBeFalse();
});

it('judges the geo rule in isolation', function (): void {
    expect($this->guard->geoAllowed('off', [], 'US'))->toBeTrue() // off ⇒ no restriction
        ->and($this->guard->geoAllowed('allow', ['AE'], 'AE'))->toBeTrue()
        ->and($this->guard->geoAllowed('allow', ['AE'], 'US'))->toBeFalse()
        ->and($this->guard->geoAllowed('deny', ['RU'], 'RU'))->toBeFalse()
        ->and($this->guard->geoAllowed('allow', ['AE'], null))->toBeTrue(); // unresolved ⇒ fail open
});

it('does not blame the IP for a geo-only block (and vice versa)', function (): void {
    // The anti-lockout message keys off these two verdicts: an empty allowlist
    // with a blocking country must report geo alone, never the IP.
    expect($this->guard->ipAllowed([], '198.51.100.7'))->toBeTrue()
        ->and($this->guard->geoAllowed('allow', ['AE'], 'US'))->toBeFalse();
});
