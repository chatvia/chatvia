<?php

use Domain\Agent\Actions\Settings\VerifyAgentDomainAction;
use Domain\Agent\Models\Agent;
use Support\Exceptions\DomainValidationException;
use Tests\Support\Agent\FakeDnsTxtResolver;

/**
 * VerifyAgentDomainAction turns a pending custom domain on only when the
 * `_chatvia.<domain>` TXT record holds the agent's token. A domain another
 * agent uses is refused with the generic words for any unusable domain.
 */
beforeEach(function (): void {
    $this->agent = Agent::factory()->create([
        'domain' => null,
        'pending_domain' => 'help.acme.test',
        'domain_verification_token' => 'chatvia-verify-abc',
    ]);
});

it('turns the domain on when the TXT record holds the token', function (): void {
    $dns = FakeDnsTxtResolver::bind(['_chatvia.help.acme.test' => ['other', '"chatvia-verify-abc"']]);

    app(VerifyAgentDomainAction::class)->execute($this->agent);

    expect($this->agent->fresh())->domain->toBe('help.acme.test')->pending_domain->toBeNull()
        ->and($dns->asked)->toBe(['_chatvia.help.acme.test']);
});

it('leaves the domain pending without the record', function (array $records): void {
    FakeDnsTxtResolver::bind($records);

    expect(fn () => app(VerifyAgentDomainAction::class)->execute($this->agent))
        ->toThrow(DomainValidationException::class, "We couldn't find the TXT record");

    expect($this->agent->fresh())->domain->toBeNull()->pending_domain->toBe('help.acme.test');
})->with([
    'no record' => [[]],
    'another token' => [['_chatvia.help.acme.test' => ['chatvia-verify-xyz']]],
    'the token on the domain itself' => [['help.acme.test' => ['chatvia-verify-abc']]],
]);

it('refuses a domain another agent already uses, without saying so', function (): void {
    FakeDnsTxtResolver::bind(['_chatvia.help.acme.test' => ['chatvia-verify-abc']]);
    Agent::factory()->create(['domain' => 'help.acme.test']);

    expect(fn () => app(VerifyAgentDomainAction::class)->execute($this->agent))
        ->toThrow(DomainValidationException::class, "This domain can't be used.");

    expect($this->agent->fresh()->domain)->toBeNull();
});

it('refuses when nothing is pending', function (): void {
    $dns = FakeDnsTxtResolver::bind();
    $this->agent->update(['pending_domain' => null]);

    expect(fn () => app(VerifyAgentDomainAction::class)->execute($this->agent))
        ->toThrow(DomainValidationException::class, 'Add a custom domain first.');
    expect($dns->asked)->toBe([]);
});
