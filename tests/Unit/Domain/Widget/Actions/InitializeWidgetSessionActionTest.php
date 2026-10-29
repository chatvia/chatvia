<?php

use Domain\Agent\Models\Agent;
use Domain\Conversation\Models\Conversation;
use Domain\Conversation\Models\Session;
use Domain\Widget\Actions\InitializeWidgetSessionAction;
use Domain\Widget\Data\Session\WidgetSessionInitData;

/**
 * InitializeWidgetSessionAction sits at the multi-tenant boundary: the widget
 * presents an "existing_session_id" cookie value which we have to verify
 * belongs to THIS agent before reusing it. A mistake here would let an
 * attacker who got hold of any session id from any other agent on the
 * platform resurface conversations under their own widget mount.
 *  - Reusing a valid same-agent session does not mint a new one.
 *  - An existing session id that belongs to a DIFFERENT agent must be
 *    ignored, and a fresh session minted for the requesting agent.
 *  - A remembered conversation is resumed only inside its own session.
 *  - is_new_session/is_new_conversation flags reflect what actually happened.
 *  - The visitor's referrer and page URLs are stored only when they are
 *    http(s), so an operator never opens a javascript: link.
 */
function iwsInitData(?string $existingSessionId = null): WidgetSessionInitData
{
    return WidgetSessionInitData::from([
        'existingSessionId' => $existingSessionId,
        'existingConversationId' => null,
        'ipAddress' => '127.0.0.1',
        'userAgent' => 'Mozilla/5.0',
        'acceptLanguage' => null,
        'referrerUrl' => 'https://example.com',
        'pageUrl' => 'https://example.com',
    ]);
}

it('reuses a session only of the same agent, and mints a fresh one for another agent\'s session id', function (): void {
    $agent = Agent::factory()->create();
    $existing = Session::factory()->create(['agent_id' => $agent->id, 'last_activity_at' => now()->subDay()]);
    $foreignSession = Session::factory()->create(['agent_id' => Agent::factory()->create()->id]);

    $reused = app(InitializeWidgetSessionAction::class)->execute($agent, iwsInitData(existingSessionId: $existing->external_id));
    $minted = app(InitializeWidgetSessionAction::class)->execute($agent, iwsInitData(existingSessionId: $foreignSession->external_id));

    expect($reused->session_id)->toBe($existing->external_id)
        ->and($reused->is_new_session)->toBeFalse()
        ->and($minted->session_id)->not->toBe($foreignSession->external_id)
        ->and($minted->is_new_session)->toBeTrue()
        ->and(Session::query()->where('external_id', $minted->session_id)->value('agent_id'))->toBe($agent->id);
});

it('resumes a remembered conversation only inside its own session', function (): void {
    $agent = Agent::factory()->create();
    $first = app(InitializeWidgetSessionAction::class)->execute($agent, iwsInitData());

    $resumed = app(InitializeWidgetSessionAction::class)->execute($agent, WidgetSessionInitData::from([
        ...iwsInitData(existingSessionId: $first->session_id)->toArray(),
        'existingConversationId' => $first->conversation_id,
    ]));
    $elsewhere = app(InitializeWidgetSessionAction::class)->execute($agent, WidgetSessionInitData::from([
        ...iwsInitData()->toArray(),
        'existingConversationId' => $first->conversation_id,
    ]));

    expect($resumed->conversation_id)->toBe($first->conversation_id)
        ->and($resumed->is_new_conversation)->toBeFalse()
        ->and($resumed->is_new_session)->toBeFalse()
        ->and($elsewhere->is_new_session)->toBeTrue()
        ->and($elsewhere->conversation_id)->not->toBe($first->conversation_id)
        ->and($elsewhere->is_new_conversation)->toBeTrue()
        ->and(Conversation::query()->where('external_id', $elsewhere->conversation_id)->firstOrFail()->session->external_id)->toBe($elsewhere->session_id);
});

it('stores the visitor\'s referrer and page URLs only when they are http(s)', function (?string $referrer, ?string $page, ?string $storedReferrer, ?string $storedPage): void {
    $agent = Agent::factory()->create();

    $result = app(InitializeWidgetSessionAction::class)->execute($agent, WidgetSessionInitData::from([
        ...iwsInitData()->toArray(),
        'referrerUrl' => $referrer,
        'pageUrl' => $page,
    ]));

    $session = Session::query()->where('external_id', $result->session_id)->firstOrFail();
    expect($session->referrer_url)->toBe($storedReferrer)
        ->and($session->page_url)->toBe($storedPage);
})->with([
    'http and https' => ['https://search.example/?q=help', 'http://shop.example/pricing', 'https://search.example/?q=help', 'http://shop.example/pricing'],
    'the referrer standing in for a missing page' => ['https://shop.example/pricing', null, 'https://shop.example/pricing', 'https://shop.example/pricing'],
    'javascript' => ['javascript:alert(document.cookie)', 'javascript:alert(document.cookie)', null, null],
    'upper-case javascript' => ['JavaScript:alert(1)', 'JavaScript:alert(1)', null, null],
    'data' => ['data:text/html;base64,PHNjcmlwdD5hbGVydCgxKTwvc2NyaXB0Pg==', 'data:text/html;base64,PHNjcmlwdD5hbGVydCgxKTwvc2NyaXB0Pg==', null, null],
    'ftp' => ['ftp://files.example/report.pdf', 'ftp://files.example/report.pdf', null, null],
    'no scheme' => ['shop.example/pricing', 'shop.example/pricing', null, null],
]);
