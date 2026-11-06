<?php

use App\Identity\Workspace\Models\User;
use Illuminate\Support\Facades\Context;
use Spectra\Facades\Spectra;

/**
 * Tenancy: a workspace's dashboard shows the calls attributed to it, so a call
 * must land on the workspace its request or job carries, and on no other.
 */
it('attributes a call to the workspace the context carries', function (): void {
    $workspace = User::factory()->create()->workspace();
    Context::addHidden('workspace_id', $workspace->id);

    $call = Spectra::startRequest('openai', 'gpt-4o');

    expect($call->trackableType)->toBe('workspace')
        ->and($call->trackableId)->toBe((string) $workspace->id);
});

it('attributes a call with no workspace to none', function (): void {
    expect(Spectra::startRequest('openai', 'gpt-4o')->trackableType)->toBeNull();
});

it('keeps a trackable the call names itself', function (): void {
    Context::addHidden('workspace_id', 1);

    $call = Spectra::startRequest('openai', 'gpt-4o', ['trackable_type' => 'agent', 'trackable_id' => 9]);

    expect($call->trackableType)->toBe('agent')
        ->and($call->trackableId)->toBe(9);
});
