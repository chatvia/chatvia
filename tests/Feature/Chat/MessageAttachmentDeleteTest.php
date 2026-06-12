<?php

use App\Identity\Workspace\Models\User;
use Domain\Agent\Models\Agent;
use Domain\Billing\Services\UserCreditSystem;
use Domain\Conversation\Models\Attachment;
use Domain\Conversation\Models\Conversation;
use Domain\Conversation\Models\Message;
use Domain\Conversation\Models\Session;
use Domain\Conversation\Services\Memory\ChatCache;
use Domain\Membership\Team\Services\TeamService;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * HTTP coverage for the sent-message attachment delete endpoint. This is the
 * companion to the composer-stage delete (AttachmentDeleteTest): same
 * underlying Attachment model + S3 hook, but a different route and
 * authorization model — the route binds {conversationId} so the chat
 * middleware enforces ChatCache::isOwnedBy() on protected agents, and the
 * controller defensively cross-checks the URL's conversation against the
 * row's conversation + the current agent.
 */
function messageAgent(bool $isPublic = true): Agent
{
    $owner = User::factory()->create();
    app(TeamService::class)->createDefaultTeam($owner);
    $priceId = 'price_test_pro_'.Str::random(6);
    config(['billing.packages.pro.stripe_monthly_price_id' => $priceId]);

    // Registration is trial-checkout-only now (the Stripe webhook provisions
    // billing), so factory users have no UserBilling row — create it explicitly.
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
        'name' => 'Msg Bot',
        'external_id' => Str::random(14),
        'sub_domain' => Str::lower(Str::random(12)),
        'is_public' => $isPublic,
    ]);
    app(UserCreditSystem::class)->setUserId($agent->user_id)->setCanSendMessages(true);

    return $agent;
}

function messageConversation(Agent $agent, ?User $user = null): Conversation
{
    $session = Session::create(['agent_id' => $agent->id, 'user_id' => $user?->id]);

    return Conversation::create([
        'chat_session_id' => $session->id,
        'agent_id' => $session->agent_id,
        'source' => 'hosted_chat',
        'is_visible' => true,
        'title' => 'Test',
    ]);
}

function messageAttachment(Agent $agent, Conversation $conversation, Message $message): Attachment
{
    $filepath = sprintf('chat-uploads/agent-%d/%s/%s.pdf', $agent->id, $conversation->external_id, Str::uuid());
    Storage::disk('public')->put($filepath, 'fake');

    return Attachment::create([
        'chat_conversation_id' => $conversation->id,
        'chat_message_id' => $message->id,
        'filepath' => $filepath,
        'filename' => 'job-posting.pdf',
        'mime_type' => 'application/pdf',
        'size_bytes' => 4,
    ]);
}

beforeEach(function (): void {
    Storage::fake('public');
});

it('deletes a sent-message attachment on a public agent and wipes the S3 object', function (): void {
    $agent = messageAgent();
    $conversation = messageConversation($agent);
    $message = Message::create([
        'chat_conversation_id' => $conversation->id,
        'role' => 'user',
        'content' => 'see attached',
    ]);
    $attachment = messageAttachment($agent, $conversation, $message);

    $response = $this->delete(
        "https://chatvia.test/chat/conversations/{$conversation->external_id}/attachments/{$attachment->external_id}?agent={$agent->external_id}",
    );

    $response->assertNoContent();
    expect(Attachment::find($attachment->id))->toBeNull();
    Storage::disk('public')->assertMissing($attachment->filepath);
    // The Message survives the cascade (chat_message_id is nullOnDelete).
    expect(Message::find($message->id))->not->toBeNull();
});

it('refuses when the URL conversation id does not match the attachment row', function (): void {
    $agent = messageAgent();
    $conversation = messageConversation($agent);
    $otherConversation = messageConversation($agent);
    $message = Message::create([
        'chat_conversation_id' => $conversation->id,
        'role' => 'user',
        'content' => '...',
    ]);
    $attachment = messageAttachment($agent, $conversation, $message);

    $response = $this->delete(
        "https://chatvia.test/chat/conversations/{$otherConversation->external_id}/attachments/{$attachment->external_id}?agent={$agent->external_id}",
    );

    $response->assertForbidden();
    expect(Attachment::find($attachment->id))->not->toBeNull();
    Storage::disk('public')->assertExists($attachment->filepath);
});

it('refuses on a private agent when the requester does not own the conversation', function (): void {
    $agent = messageAgent(isPublic: false);
    $owner = User::factory()->create();
    $intruder = User::factory()->create();

    $conversation = messageConversation($agent, $owner);
    ChatCache::rememberOwner($conversation->external_id, $owner->id);
    $message = Message::create([
        'chat_conversation_id' => $conversation->id,
        'role' => 'user',
        'content' => '...',
    ]);
    $attachment = messageAttachment($agent, $conversation, $message);

    $response = $this->actingAs($intruder)->delete(
        "https://chatvia.test/chat/conversations/{$conversation->external_id}/attachments/{$attachment->external_id}?agent={$agent->external_id}",
    );

    $response->assertForbidden();
    expect(Attachment::find($attachment->id))->not->toBeNull();
});

it('lets the rightful owner delete on a private agent', function (): void {
    $agent = messageAgent(isPublic: false);
    $owner = User::factory()->create();

    $conversation = messageConversation($agent, $owner);
    ChatCache::rememberOwner($conversation->external_id, $owner->id);
    $message = Message::create([
        'chat_conversation_id' => $conversation->id,
        'role' => 'user',
        'content' => '...',
    ]);
    $attachment = messageAttachment($agent, $conversation, $message);

    $response = $this->actingAs($owner)->delete(
        "https://chatvia.test/chat/conversations/{$conversation->external_id}/attachments/{$attachment->external_id}?agent={$agent->external_id}",
    );

    $response->assertNoContent();
    expect(Attachment::find($attachment->id))->toBeNull();
    Storage::disk('public')->assertMissing($attachment->filepath);
});

it('returns 404 for a non-existent attachment external_id', function (): void {
    $agent = messageAgent();
    $conversation = messageConversation($agent);

    $response = $this->delete(
        "https://chatvia.test/chat/conversations/{$conversation->external_id}/attachments/01999999-0000-7000-8000-000000000000?agent={$agent->external_id}",
    );

    $response->assertNotFound();
});
