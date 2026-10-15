<?php

use App\Identity\Chatter\Actions\CreateChatterAction;
use App\Identity\Chatter\Data\CreateChatterData;
use App\Identity\Chatter\Models\Chatter;
use Domain\Agent\Models\Agent;
use Domain\Conversation\Data\Memory\ChatViewerData;
use Domain\Conversation\Enums\ConversationSource;
use Domain\Conversation\Models\Conversation;
use Domain\Conversation\Models\Session;
use Domain\Conversation\Services\Memory\ChatCache;
use Illuminate\Support\Str;

/**
 * HTTP coverage for the regenerate endpoint's authorization gate
 * (RegenerateMessageRequest). The route takes the conversation id in the body,
 * so the gate is re-applied in the FormRequest. These lock in the split that
 * keeps a missing/deleted/foreign conversation a 404 ("gone") while a genuine
 * private-agent ownership failure stays a 403 — so a stale tab regenerating a
 * thread deleted elsewhere reports the honest status instead of "forbidden".
 */
/** A hosted-chat conversation of the agent, in a session of the chatter (or of a visitor). */
function regenerateConversation(Agent $agent, ?Chatter $chatter = null): Conversation
{
    return Conversation::factory()->create([
        'chat_session_id' => Session::factory()->create(['agent_id' => $agent->id, 'chatter_id' => $chatter?->id])->id,
        'source' => ConversationSource::HostedChat,
        'title' => 'Test chat',
    ]);
}

function regeneratePayload(Conversation $conversation): array
{
    return [
        'conversation_id' => $conversation->external_id,
        'user_message_id' => (string) Str::uuid(),
        'assistant_message_id' => (string) Str::uuid(),
    ];
}

it('returns 404 (not 403) when the conversation has been soft-deleted', function (): void {
    $agent = Agent::factory()->create(['is_public' => true]);
    $conversation = regenerateConversation($agent);
    $conversation->forceFill(['deleted_at' => now()])->saveQuietly();

    $response = $this->postJson(
        "/chat/{$agent->external_id}/messages/regenerate",
        regeneratePayload($conversation),
    );

    $response->assertNotFound();
});

it('returns 404 for a conversation id that does not exist', function (): void {
    $agent = Agent::factory()->create(['is_public' => true]);

    $response = $this->postJson(
        "/chat/{$agent->external_id}/messages/regenerate",
        [
            'conversation_id' => (string) Str::uuid(),
            'user_message_id' => (string) Str::uuid(),
            'assistant_message_id' => (string) Str::uuid(),
        ],
    );

    $response->assertNotFound();
});

it('returns 404 when the conversation belongs to another agent', function (): void {
    $otherAgent = Agent::factory()->create(['is_public' => true]);
    $conversation = regenerateConversation($otherAgent);

    $requesterAgent = Agent::factory()->create(['is_public' => true]);

    $response = $this->postJson(
        "/chat/{$requesterAgent->external_id}/messages/regenerate",
        regeneratePayload($conversation),
    );

    $response->assertNotFound();
});

it('returns 403 on a private agent when the auth user does not own the conversation', function (): void {
    // Positive control: the conversation exists and belongs to the agent (so it
    // gets PAST the 404 "gone" branch); only the ownership gate refuses it.
    $agent = Agent::factory()->create(['is_public' => false]);
    $owner = Chatter::factory()->grantedTo($agent)->create();

    $intruder = app(CreateChatterAction::class)->execute(new CreateChatterData($agent->workspace, $agent, 'Intruder'))->refresh();

    $conversation = regenerateConversation($agent, $owner);
    ChatCache::rememberOwner($conversation->external_id, ChatViewerData::chatter($owner->id), $agent->workspaceExternalId());

    $response = $this->actingAs($intruder, 'chat')->postJson(
        "/chat/{$agent->external_id}/messages/regenerate",
        regeneratePayload($conversation),
    );

    $response->assertForbidden();
});
