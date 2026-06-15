<?php

use App\Identity\Chatter\Actions\DeleteChatterAction;
use App\Identity\Workspace\Models\User;
use Domain\Agent\Models\Agent;
use Domain\Conversation\Enums\ConversationSource;
use Domain\Conversation\Models\Attachment;
use Domain\Conversation\Models\Conversation;
use Domain\Conversation\Models\Session as ChatSession;
use Domain\Conversation\Services\Memory\ChatCache;
use Domain\Lead\Models\Lead;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    // Chatter deletion wipes the avatar tree on the public bucket; fake it so
    // the assertions stay hermetic and never hit real S3.
    Storage::fake('public');
});

it('hard-deletes the chatter row', function (): void {
    $workspace = User::factory()->workspace()->create();
    $chatter = User::factory()->chatter($workspace)->create();

    app(DeleteChatterAction::class)->execute($chatter);

    expect(User::whereKey($chatter->id)->exists())->toBeFalse();
});

it('does not affect other chatters belonging to the same workspace', function (): void {
    $workspace = User::factory()->workspace()->create();
    $chatter = User::factory()->chatter($workspace)->create();
    $sibling = User::factory()->chatter($workspace)->create();

    app(DeleteChatterAction::class)->execute($chatter);

    expect(User::whereKey($chatter->id)->exists())->toBeFalse()
        ->and(User::whereKey($sibling->id)->exists())->toBeTrue();
});

it('flushes the deleted chatter chat memory (session + ownership marker) and leaves siblings intact', function (): void {
    // The chat cache is Redis in config; array driver exercises the tagged
    // flush without a live Redis (mirrors the memory unit tests).
    config(['cache.stores.chat' => ['driver' => 'array']]);

    $workspace = User::factory()->workspace()->create();
    $chatter = User::factory()->chatter($workspace)->create();
    $sibling = User::factory()->chatter($workspace)->create();
    $agent = Agent::factory()->create(['user_id' => $workspace->id]);

    // The chatter's session + conversation, seeded exactly as the app writes:
    // session-data under [workspace, agent, session]; ownership marker (forever)
    // under [conversation, workspace].
    $session = ChatSession::create(['agent_id' => $agent->id, 'user_id' => $chatter->id]);
    $conversation = Conversation::create([
        'chat_session_id' => $session->id,
        'agent_id' => $agent->id,
        'source' => ConversationSource::HostedChat,
        'title' => 'Chatter chat',
    ]);
    Cache::driver('chat')->tags(ChatCache::sessionTags($workspace->external_id, $agent->external_id, $session->external_id))->forever($session->external_id, ['conversations' => []]);
    ChatCache::rememberOwner($conversation->external_id, $chatter->id, $workspace->external_id);

    // A sibling's session in the same workspace must survive.
    $siblingSession = ChatSession::create(['agent_id' => $agent->id, 'user_id' => $sibling->id]);
    Cache::driver('chat')->tags(ChatCache::sessionTags($workspace->external_id, $agent->external_id, $siblingSession->external_id))->forever($siblingSession->external_id, ['conversations' => []]);

    expect(ChatCache::isOwnedBy($conversation->external_id, $chatter->id, $workspace->external_id))->toBeTrue();

    app(DeleteChatterAction::class)->execute($chatter);

    expect(Cache::driver('chat')->tags(ChatCache::sessionTags($workspace->external_id, $agent->external_id, $session->external_id))->has($session->external_id))->toBeFalse()
        ->and(ChatCache::isOwnedBy($conversation->external_id, $chatter->id, $workspace->external_id))->toBeFalse()
        ->and(Cache::driver('chat')->tags(ChatCache::sessionTags($workspace->external_id, $agent->external_id, $siblingSession->external_id))->has($siblingSession->external_id))->toBeTrue();
});

it('wipes the deleted chatter conversation attachment files on S3', function (): void {
    config(['cache.stores.chat' => ['driver' => 'array']]);
    Storage::fake('public');

    $workspace = User::factory()->workspace()->create();
    $chatter = User::factory()->chatter($workspace)->create();
    $agent = Agent::factory()->create(['user_id' => $workspace->id]);

    $session = ChatSession::create(['agent_id' => $agent->id, 'user_id' => $chatter->id]);
    $conversation = Conversation::create([
        'chat_session_id' => $session->id,
        'agent_id' => $agent->id,
        'source' => ConversationSource::HostedChat,
        'title' => 'With attachment',
    ]);

    $filepath = "users/{$workspace->external_id}/agents/{$agent->external_id}/attachments/pending/doc.pdf";
    Storage::disk('public')->put($filepath, 'data');
    $attachment = Attachment::create([
        'chat_conversation_id' => $conversation->id,
        'filepath' => $filepath,
        'filename' => 'doc.pdf',
        'mime_type' => 'application/pdf',
        'size_bytes' => 4,
    ]);

    Storage::disk('public')->assertExists($filepath);

    app(DeleteChatterAction::class)->execute($chatter);

    Storage::disk('public')->assertMissing($filepath);
    expect(Attachment::whereKey($attachment->id)->exists())->toBeFalse();
});

it('wipes the deleted chatter profile photo on S3', function (): void {
    config(['cache.stores.chat' => ['driver' => 'array']]);

    $workspace = User::factory()->workspace()->create();
    $chatter = User::factory()->chatter($workspace)->create();

    $photoPath = 'users/'.$chatter->external_id.'/profile-photos/avatar.jpg';
    Storage::disk('public')->put($photoPath, 'img');
    $chatter->update(['profile_image_path' => $photoPath]);

    Storage::disk('public')->assertExists($photoPath);

    app(DeleteChatterAction::class)->execute($chatter);

    Storage::disk('public')->assertMissing($photoPath);
});

it('deletes leads captured from the chatter conversations and leaves other chatters leads intact', function (): void {
    config(['cache.stores.chat' => ['driver' => 'array']]);

    $workspace = User::factory()->workspace()->create();
    $chatter = User::factory()->chatter($workspace)->create();
    $sibling = User::factory()->chatter($workspace)->create();
    $agent = Agent::factory()->create(['user_id' => $workspace->id]);

    $session = ChatSession::create(['agent_id' => $agent->id, 'user_id' => $chatter->id]);
    $conversation = Conversation::create([
        'chat_session_id' => $session->id,
        'agent_id' => $agent->id,
        'source' => ConversationSource::HostedChat,
        'title' => 'Lead chat',
    ]);
    $lead = Lead::factory()->forAgent($agent)->withConversation($conversation)->create();

    // A sibling chatter's lead on a different conversation must survive.
    $siblingSession = ChatSession::create(['agent_id' => $agent->id, 'user_id' => $sibling->id]);
    $siblingConversation = Conversation::create([
        'chat_session_id' => $siblingSession->id,
        'agent_id' => $agent->id,
        'source' => ConversationSource::HostedChat,
        'title' => 'Sibling chat',
    ]);
    $siblingLead = Lead::factory()->forAgent($agent)->withConversation($siblingConversation)->create();

    app(DeleteChatterAction::class)->execute($chatter);

    expect(Lead::whereKey($lead->id)->exists())->toBeFalse()
        ->and(Lead::whereKey($siblingLead->id)->exists())->toBeTrue();
});
