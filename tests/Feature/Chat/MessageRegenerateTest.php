<?php

use App\Identity\Chatter\Actions\CreateChatterAction;
use App\Identity\Chatter\Data\CreateChatterData;
use App\Identity\Workspace\Models\User;
use Domain\Agent\Models\Agent;
use Domain\Billing\Services\UserCreditSystem;
use Domain\Conversation\Models\Conversation;
use Domain\Conversation\Models\Session;
use Domain\Conversation\Services\Memory\ChatCache;
use Domain\Membership\Team\Services\TeamService;
use Illuminate\Support\Str;

/**
 * HTTP coverage for the regenerate endpoint's authorization gate
 * (RegenerateMessageRequest). The route takes the conversation id in the body,
 * so the gate is re-applied in the FormRequest. These lock in the split that
 * keeps a missing/deleted/foreign conversation a 404 ("gone") while a genuine
 * private-agent ownership failure stays a 403 — so a stale tab regenerating a
 * thread deleted elsewhere reports the honest status instead of "forbidden".
 */
function regenerateAgent(bool $isPublic = true): Agent
{
    $owner = User::factory()->create();
    app(TeamService::class)->createDefaultTeam($owner);

    $priceId = 'price_test_pro_'.Str::random(6);
    config(['billing.packages.pro.stripe_monthly_price_id' => $priceId]);

    $owner->billing()->firstOrCreate([]);
    $owner->billing->subscriptions()->create([
        'type' => 'default',
        'stripe_id' => 'sub_test_'.Str::random(8),
        'stripe_status' => 'active',
        'stripe_price' => $priceId,
        'quantity' => 1,
    ]);
    $owner->refresh();

    $agent = Agent::create([
        'user_id' => $owner->id,
        'team_id' => $owner->primaryTeam()->id,
        'name' => 'Regenerate Bot',
        'external_id' => Str::random(14),
        'sub_domain' => Str::lower(Str::random(12)),
        'system_instructions' => 'You are a helpful assistant.',
        'is_public' => $isPublic,
    ]);

    app(UserCreditSystem::class)->forWorkspace($agent->user_id)->setCanSendMessages(true);

    return $agent;
}

function regenerateConversation(Agent $agent, ?User $user = null): Conversation
{
    $session = Session::create([
        'agent_id' => $agent->id,
        'user_id' => $user?->id,
    ]);

    return Conversation::create([
        'chat_session_id' => $session->id,
        'agent_id' => $session->agent_id,
        'source' => 'hosted_chat',
        'is_visible' => true,
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
    $agent = regenerateAgent();
    $conversation = regenerateConversation($agent);
    $conversation->forceFill(['deleted_at' => now()])->saveQuietly();

    $response = $this->postJson(
        "https://chatvia.test/chat/messages/regenerate?agent={$agent->external_id}",
        regeneratePayload($conversation),
    );

    $response->assertNotFound();
});

it('returns 404 for a conversation id that does not exist', function (): void {
    $agent = regenerateAgent();

    $response = $this->postJson(
        "https://chatvia.test/chat/messages/regenerate?agent={$agent->external_id}",
        [
            'conversation_id' => (string) Str::uuid(),
            'user_message_id' => (string) Str::uuid(),
            'assistant_message_id' => (string) Str::uuid(),
        ],
    );

    $response->assertNotFound();
});

it('returns 404 when the conversation belongs to another agent', function (): void {
    $otherAgent = regenerateAgent();
    $conversation = regenerateConversation($otherAgent);

    $requesterAgent = regenerateAgent();

    $response = $this->postJson(
        "https://chatvia.test/chat/messages/regenerate?agent={$requesterAgent->external_id}",
        regeneratePayload($conversation),
    );

    $response->assertNotFound();
});

it('returns 403 on a private agent when the auth user does not own the conversation', function (): void {
    // Positive control: the conversation exists and belongs to the agent (so it
    // gets PAST the 404 "gone" branch); only the ownership gate refuses it.
    $agent = regenerateAgent(isPublic: false);
    $owner = User::factory()->create();

    $intruder = app(CreateChatterAction::class)->execute(new CreateChatterData($agent->user, $agent, 'Intruder'))->refresh();

    $conversation = regenerateConversation($agent, $owner);
    ChatCache::rememberOwner($conversation->external_id, $owner->id, $agent->workspaceExternalId());

    $response = $this->actingAs($intruder)->postJson(
        "https://chatvia.test/chat/messages/regenerate?agent={$agent->external_id}",
        regeneratePayload($conversation),
    );

    $response->assertForbidden();
});
