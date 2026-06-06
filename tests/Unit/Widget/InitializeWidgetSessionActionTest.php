<?php

use Domain\Agent\Models\Agent;
use Domain\Conversation\Models\Session;
use Domain\Widget\Actions\InitializeWidgetSessionAction;
use Domain\Widget\Data\Session\WidgetSessionInitData;

/**
 * InitializeWidgetSessionAction sits at the multi-tenant boundary: the widget
 * presents an "existing_session_id" cookie value which we have to verify
 * belongs to THIS agent before reusing it. A regression here would let an
 * attacker who got hold of any session id from any other agent on the
 * platform resurface conversations under their own widget mount.
 *  - Reusing a valid same-agent session does not mint a new one.
 *  - An existing session id that belongs to a DIFFERENT agent must be
 *    ignored, and a fresh session minted for the requesting agent.
 *  - is_new_session/is_new_conversation flags reflect what actually happened.
 *  - Accept-Language is parsed down to the 2-letter ISO code on a freshly
 *    minted session; default 'en' when absent.
 */
function iwsInitData(?string $existingSessionId = null, ?string $acceptLanguage = null): WidgetSessionInitData
{
    return WidgetSessionInitData::from([
        'existingSessionId' => $existingSessionId,
        'existingConversationId' => null,
        'ipAddress' => '127.0.0.1',
        'userAgent' => 'Mozilla/5.0',
        'acceptLanguage' => $acceptLanguage,
        'referrerUrl' => 'https://example.com',
        'pageUrl' => 'https://example.com',
    ]);
}

it('reuses a valid same-agent session and does not mint a new one', function (): void {
    $agent = Agent::factory()->create();
    $existing = Session::factory()->create([
        'agent_id' => $agent->id,
        'last_activity_at' => now()->subDay(),
    ]);

    $result = app(InitializeWidgetSessionAction::class)->execute(
        $agent,
        iwsInitData(existingSessionId: $existing->external_id),
    );

    expect($result->session_id)->toBe($existing->external_id)
        ->and($result->is_new_session)->toBeFalse();
});

it('ignores a session id from a DIFFERENT agent and mints a fresh one for the requesting agent', function (): void {
    $agent = Agent::factory()->create();
    $otherAgent = Agent::factory()->create();
    $foreignSession = Session::factory()->create(['agent_id' => $otherAgent->id]);

    $result = app(InitializeWidgetSessionAction::class)->execute(
        $agent,
        iwsInitData(existingSessionId: $foreignSession->external_id),
    );

    expect($result->session_id)->not->toBe($foreignSession->external_id)
        ->and($result->is_new_session)->toBeTrue();

    // And the freshly minted row really does belong to $agent (not $otherAgent).
    $mint = Session::query()->where('external_id', $result->session_id)->first();
    expect($mint)->not->toBeNull()
        ->and($mint->agent_id)->toBe($agent->id);
});

it('parses Accept-Language down to its 2-letter ISO code on a fresh session', function (): void {
    $agent = Agent::factory()->create();

    $result = app(InitializeWidgetSessionAction::class)->execute(
        $agent,
        iwsInitData(acceptLanguage: 'fr-FR,fr;q=0.9,en-US;q=0.8'),
    );

    $mint = Session::query()->where('external_id', $result->session_id)->first();
    expect($mint->locale)->toBe('fr');
});

it('defaults locale to en when no Accept-Language is provided', function (): void {
    $agent = Agent::factory()->create();

    $result = app(InitializeWidgetSessionAction::class)->execute(
        $agent,
        iwsInitData(acceptLanguage: null),
    );

    $mint = Session::query()->where('external_id', $result->session_id)->first();
    expect($mint->locale)->toBe('en');
});
