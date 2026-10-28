<?php

use Domain\Agent\Actions\Domain\VerifyAgentDomainAction;
use Domain\Agent\Enums\DomainCheck;
use Domain\Agent\Models\AgentDomain;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * VerifyAgentDomainAction: a domain serves the chat only once a check reached
 * this install through it over HTTPS, says why when it did not, and a
 * verified one stops serving only after three failed checks in a row.
 */
beforeEach(function (): void {
    $this->domain = AgentDomain::factory()->create(['host' => 'help.acme.com']);
    $this->token = $this->domain->verification_token;
});

it('records what the domain answered', function (array $answers, DomainCheck $check, bool $isVerified): void {
    Http::fake($answers);

    $domain = app(VerifyAgentDomainAction::class)->execute($this->domain);

    expect($domain->fresh())
        ->last_check->toBe($check)
        ->isVerified()->toBe($isVerified)
        ->last_checked_at->not->toBeNull();
})->with([
    'this install over HTTPS' => [fn (): array => ['https://help.acme.com/*' => Http::response($this->token)], DomainCheck::Reached, true],
    'this install, but only over HTTP' => [fn (): array => [
        'https://help.acme.com/*' => fn () => throw new ConnectionException('SSL certificate problem'),
        'http://help.acme.com/*' => Http::response($this->token),
    ], DomainCheck::NoCertificate, false],
    'another server' => [fn (): array => ['*' => Http::response('Welcome to nginx!')], DomainCheck::PointsElsewhere, false],
    'nothing' => [fn (): array => ['*' => fn () => throw new ConnectionException('Could not resolve host')], DomainCheck::NotReached, false],
]);

it('keeps a verified domain through two failed checks, and unverifies it on the third', function (): void {
    Http::fake(['*' => fn () => throw new ConnectionException('Could not resolve host')]);
    $this->domain->update(['verified_at' => now()]);
    $verify = app(VerifyAgentDomainAction::class);

    $verify->execute($this->domain);
    $verify->execute($this->domain);

    expect($this->domain->fresh()->isVerified())->toBeTrue();

    $verify->execute($this->domain);

    expect($this->domain->fresh())
        ->isVerified()->toBeFalse()
        ->failed_checks->toBe(3);
});
