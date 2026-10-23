<?php

use Domain\Agent\Models\Agent;
use Illuminate\Support\Facades\DB;

/**
 * The JWT signing secret is seeded at creation so the read-only
 * identity/preview render paths never trigger a lazy DB write on first hit.
 * (auth_secret is an `encrypted` cast — the raw column is ciphertext, the
 * attribute decrypts to the cvsk_ value.)
 */
it('seeds an auth secret as part of agent creation', function (): void {
    $agent = Agent::factory()->create();

    expect($agent->auth_secret)->toBeString()
        ->and($agent->auth_secret)->toStartWith('cvsk_')
        // Persisted on the insert: a fresh fetch decrypts to the same secret.
        ->and(Agent::find($agent->id)->auth_secret)->toBe($agent->auth_secret);
});

it('reads the seeded secret without writing on ensureAuthSecret()', function (): void {
    $agent = Agent::factory()->create();
    $ciphertextBefore = DB::table('agents')->where('id', $agent->id)->value('auth_secret');

    $returned = Agent::find($agent->id)->ensureAuthSecret();

    $ciphertextAfter = DB::table('agents')->where('id', $agent->id)->value('auth_secret');

    // Same secret returned, and the stored ciphertext is byte-for-byte
    // unchanged — i.e. the read did not persist anything.
    expect($returned)->toBe($agent->auth_secret)
        ->and($ciphertextAfter)->toBe($ciphertextBefore);
});
