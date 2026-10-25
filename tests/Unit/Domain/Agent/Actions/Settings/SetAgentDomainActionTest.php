<?php

use Domain\Agent\Actions\Settings\SetAgentDomainAction;
use Domain\Agent\Models\Agent;

/**
 * SetAgentDomainAction keeps a new custom domain pending, with a verification
 * token, until its TXT record is verified; the domain in use stays until then.
 * The form request checks the value.
 */
it('keeps a new custom domain pending and gives the agent a token', function (): void {
    $agent = Agent::factory()->create(['domain' => null]);

    app(SetAgentDomainAction::class)->execute($agent, 'help.acme.com');

    $agent->refresh();

    expect($agent->domain)->toBeNull()
        ->and($agent->pending_domain)->toBe('help.acme.com')
        ->and($agent->domain_verification_token)->toStartWith('chatvia-verify-');
});

it('keeps the domain in use and the token while a new one is pending', function (): void {
    $agent = Agent::factory()->create(['domain' => 'old.acme.com', 'domain_verification_token' => 'chatvia-verify-same']);

    app(SetAgentDomainAction::class)->execute($agent, 'new.acme.com');

    expect($agent->fresh())
        ->domain->toBe('old.acme.com')
        ->pending_domain->toBe('new.acme.com')
        ->domain_verification_token->toBe('chatvia-verify-same');
});

it('drops the pending domain when the domain in use is saved again, and clears both when empty', function (): void {
    $agent = Agent::factory()->create(['domain' => 'old.acme.com', 'pending_domain' => 'new.acme.com']);

    app(SetAgentDomainAction::class)->execute($agent, 'old.acme.com');
    expect($agent->fresh())->domain->toBe('old.acme.com')->pending_domain->toBeNull();

    app(SetAgentDomainAction::class)->execute($agent, null);
    expect($agent->fresh())->domain->toBeNull()->pending_domain->toBeNull();
});
