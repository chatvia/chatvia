<?php

use Domain\Agent\Models\Agent;
use Domain\Conversation\Actions\Handoff\MarkHandoffConversationReadAction;
use Domain\Conversation\Enums\ConversationSource;
use Domain\Conversation\Models\Conversation;
use Domain\Conversation\Models\Session;
use Illuminate\Support\Str;

/**
 * MarkHandoffConversationReadAction guards against marking messages read on
 * a conversation whose session belongs to a DIFFERENT agent — that's the
 * regression this test pins. The happy path delegates to ChatMemory and is
 * exercised by feature tests of the dashboard handoff flow.
 */
it('returns 0 without touching the chat memory when the conversation belongs to a different agent', function (): void {
    $agentA = Agent::factory()->create();
    $agentB = Agent::factory()->create();

    // Conversation on agent A; caller asks to mark it read FROM agent B.
    $session = Session::factory()->create(['agent_id' => $agentA->id]);
    /** @var Conversation $conversation */
    $conversation = Conversation::factory()->create([
        'chat_session_id' => $session->id,
        'external_id' => (string) Str::uuid(),
        'source' => ConversationSource::Widget,
    ]);

    $result = app(MarkHandoffConversationReadAction::class)->execute($agentB, $conversation);

    expect($result)->toBe(0);
});

it('returns 0 when the conversation has no session at all', function (): void {
    $agent = Agent::factory()->create();

    // Build a conversation whose chat_session_id points at a session that
    // gets deleted before the action runs. The action's null-safety check
    // (`if (! $session)`) is the regression guard here.
    $session = Session::factory()->create(['agent_id' => $agent->id]);
    /** @var Conversation $conversation */
    $conversation = Conversation::factory()->create([
        'chat_session_id' => $session->id,
        'external_id' => (string) Str::uuid(),
        'source' => ConversationSource::Widget,
    ]);
    $session->delete();
    $conversation->setRelation('session', null); // force the null-relation case

    $result = app(MarkHandoffConversationReadAction::class)->execute($agent, $conversation);

    expect($result)->toBe(0);
});
