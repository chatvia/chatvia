<?php

use Domain\Agent\Models\Agent;
use Domain\Conversation\Actions\Conversations\StartConversationAction;
use Domain\Conversation\Enums\ConversationSource;
use Domain\Conversation\Models\Conversation;
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
 *  - A remembered conversation is resumed only inside its own session.
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
        iwsInitData(),
    );

    $mint = Session::query()->where('external_id', $result->session_id)->first();
    expect($mint->locale)->toBe('en');
});

it('rolls back the new session when the conversation cannot be created', function (): void {
    $agent = Agent::factory()->create();
    $sessionsBefore = Session::query()->where('agent_id', $agent->id)->count();

    $startConversation = Mockery::mock(StartConversationAction::class);
    $startConversation->shouldReceive('execute')->andThrow(new RuntimeException('conversation insert failed'));
    app()->instance(StartConversationAction::class, $startConversation);

    expect(fn () => app(InitializeWidgetSessionAction::class)->execute($agent, iwsInitData()))
        ->toThrow(RuntimeException::class, 'conversation insert failed');

    expect(Session::query()->where('agent_id', $agent->id)->count())->toBe($sessionsBefore);
});

it('starts a widget conversation with the placeholder title when none is remembered', function (): void {
    $agent = Agent::factory()->create();

    $result = app(InitializeWidgetSessionAction::class)->execute($agent, iwsInitData());

    $conversation = Conversation::query()->where('external_id', $result->conversation_id)->firstOrFail();

    expect($result->is_new_conversation)->toBeTrue()
        ->and($conversation->source)->toBe(ConversationSource::Widget)
        ->and($conversation->title)->toBe(Conversation::DEFAULT_TITLE)
        ->and($conversation->session->external_id)->toBe($result->session_id);
});

it('resumes a remembered conversation of the same agent', function (): void {
    $agent = Agent::factory()->create();
    $first = app(InitializeWidgetSessionAction::class)->execute($agent, iwsInitData());

    $again = app(InitializeWidgetSessionAction::class)->execute($agent, WidgetSessionInitData::from([
        ...iwsInitData(existingSessionId: $first->session_id)->toArray(),
        'existingConversationId' => $first->conversation_id,
    ]));

    expect($again->conversation_id)->toBe($first->conversation_id)
        ->and($again->is_new_conversation)->toBeFalse()
        ->and($again->is_new_session)->toBeFalse();
});

it('starts a new conversation when the remembered one belongs to another session', function (): void {
    $agent = Agent::factory()->create();
    $first = app(InitializeWidgetSessionAction::class)->execute($agent, iwsInitData());

    // The widget lost its session id but still remembers the conversation.
    $again = app(InitializeWidgetSessionAction::class)->execute($agent, WidgetSessionInitData::from([
        ...iwsInitData()->toArray(),
        'existingConversationId' => $first->conversation_id,
    ]));

    $conversation = Conversation::query()->where('external_id', $again->conversation_id)->firstOrFail();

    expect($again->is_new_session)->toBeTrue()
        ->and($again->conversation_id)->not->toBe($first->conversation_id)
        ->and($again->is_new_conversation)->toBeTrue()
        ->and($conversation->session->external_id)->toBe($again->session_id);
});
