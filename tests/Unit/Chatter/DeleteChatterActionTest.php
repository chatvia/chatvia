<?php

/**
 * DeleteChatterAction: the chatter row and everything tied to it (sessions,
 * chat memory, attachment files, leads, avatar, open invitations) go; other
 * chatters, staff accounts and other workspaces keep theirs.
 */

use App\Identity\Chatter\Actions\DeleteChatterAction;
use App\Identity\Chatter\Models\Chatter;
use App\Identity\Workspace\Models\User;
use Domain\Agent\Models\Agent;
use Domain\Conversation\Data\Memory\ChatViewerData;
use Domain\Conversation\Enums\ConversationSource;
use Domain\Conversation\Models\Attachment;
use Domain\Conversation\Models\Conversation;
use Domain\Conversation\Models\Session as ChatSession;
use Domain\Conversation\Services\Memory\ChatCache;
use Domain\Form\Models\FormSubmission;
use Domain\Membership\Agent\Models\AgentChatterInvitation;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

beforeEach(function (): void {
    // Chatter deletion wipes the avatar tree on the assets disk and attachment
    // files on the public bucket; fake both so the assertions stay hermetic and
    // never hit real S3 / CDN storage.
    Storage::fake('public');
    Storage::fake('assets');
});

/** An open invitation of the email to the agent. */
function deleteChatterTestInvitation(Agent $agent, string $email): AgentChatterInvitation
{
    return AgentChatterInvitation::query()->create([
        'agent_id' => $agent->id,
        'email' => $email,
        'token' => Str::random(40),
        'expires_at' => now()->addDays(AgentChatterInvitation::TTL_DAYS),
    ]);
}

it('hard-deletes the chatter row', function (): void {
    $chatter = Chatter::factory()->create();

    app(DeleteChatterAction::class)->execute($chatter);

    expect(Chatter::whereKey($chatter->id)->exists())->toBeFalse();
});

it('drops the chatter agent grants', function (): void {
    $workspace = User::factory()->workspace()->create();
    $agent = createAgent($workspace);
    $chatter = Chatter::factory()->grantedTo($agent)->create();

    app(DeleteChatterAction::class)->execute($chatter);

    expect(DB::table('agent_chatter')->where('chatter_id', $chatter->id)->exists())->toBeFalse()
        ->and(Agent::whereKey($agent->id)->exists())->toBeTrue();
});

it('does not affect other chatters belonging to the same workspace', function (): void {
    $workspace = User::factory()->workspace()->create();
    $chatter = Chatter::factory()->ofWorkspace($workspace)->create();
    $sibling = Chatter::factory()->ofWorkspace($workspace)->create();

    app(DeleteChatterAction::class)->execute($chatter);

    expect(Chatter::whereKey($chatter->id)->exists())->toBeFalse()
        ->and(Chatter::whereKey($sibling->id)->exists())->toBeTrue()
        ->and(User::whereKey($workspace->id)->exists())->toBeTrue();
});

it('flushes the deleted chatter chat memory (session + ownership marker) and leaves siblings intact', function (): void {
    // The chat cache is Redis in config; array driver exercises the tagged
    // flush without a live Redis (mirrors the memory unit tests).
    config(['cache.stores.chat' => ['driver' => 'array']]);

    $workspace = User::factory()->workspace()->create();
    $chatter = Chatter::factory()->ofWorkspace($workspace)->create();
    $sibling = Chatter::factory()->ofWorkspace($workspace)->create();
    $agent = Agent::factory()->create(['workspace_id' => $workspace->workspace()->id]);
    $viewer = ChatViewerData::chatter($chatter->id);

    // The chatter's session + conversation, seeded exactly as the app writes:
    // session-data under [workspace, agent, session]; ownership marker (forever)
    // under [conversation, workspace].
    $session = ChatSession::create(['workspace_id' => $agent->workspace_id, 'agent_id' => $agent->id, 'chatter_id' => $chatter->id]);
    $conversation = Conversation::create([
        'chat_session_id' => $session->id,
        'workspace_id' => $agent->workspace_id,
        'agent_id' => $agent->id,
        'source' => ConversationSource::HostedChat,
        'title' => 'Chatter chat',
    ]);
    Cache::driver('chat')->tags(ChatCache::sessionTags($workspace->external_id, $agent->external_id, $session->external_id))->forever($session->external_id, ['conversations' => []]);
    ChatCache::rememberOwner($conversation->external_id, $viewer, $workspace->external_id);

    // A sibling's session in the same workspace must survive.
    $siblingSession = ChatSession::create(['workspace_id' => $agent->workspace_id, 'agent_id' => $agent->id, 'chatter_id' => $sibling->id]);
    Cache::driver('chat')->tags(ChatCache::sessionTags($workspace->external_id, $agent->external_id, $siblingSession->external_id))->forever($siblingSession->external_id, ['conversations' => []]);

    expect(ChatCache::isOwnedBy($conversation->external_id, $viewer, $workspace->external_id))->toBeTrue();

    app(DeleteChatterAction::class)->execute($chatter);

    expect(Cache::driver('chat')->tags(ChatCache::sessionTags($workspace->external_id, $agent->external_id, $session->external_id))->has($session->external_id))->toBeFalse()
        ->and(ChatCache::isOwnedBy($conversation->external_id, $viewer, $workspace->external_id))->toBeFalse()
        ->and(Cache::driver('chat')->tags(ChatCache::sessionTags($workspace->external_id, $agent->external_id, $siblingSession->external_id))->has($siblingSession->external_id))->toBeTrue()
        ->and(ChatSession::whereKey($session->id)->exists())->toBeFalse()
        ->and(ChatSession::whereKey($siblingSession->id)->exists())->toBeTrue();
});

it('keeps the sessions of a staff account whose id equals the chatter id', function (): void {
    config(['cache.stores.chat' => ['driver' => 'array']]);

    $workspace = User::factory()->workspace()->create();
    $staff = User::factory()->employee($workspace)->create();
    // Chatter and staff ids come from different tables and can be equal.
    $chatter = Chatter::factory()->ofWorkspace($workspace)->create(['id' => $staff->id]);
    $agent = Agent::factory()->create(['workspace_id' => $workspace->workspace()->id]);

    $chatterSession = ChatSession::create(['workspace_id' => $agent->workspace_id, 'agent_id' => $agent->id, 'chatter_id' => $chatter->id]);
    $staffSession = ChatSession::create(['workspace_id' => $agent->workspace_id, 'agent_id' => $agent->id, 'user_id' => $staff->id]);
    $staffConversation = Conversation::create([
        'chat_session_id' => $staffSession->id,
        'workspace_id' => $agent->workspace_id,
        'agent_id' => $agent->id,
        'source' => ConversationSource::HostedChat,
        'title' => 'Staff preview',
    ]);
    $staffViewer = ChatViewerData::staff($staff->id);
    ChatCache::rememberOwner($staffConversation->external_id, $staffViewer, $workspace->external_id);

    app(DeleteChatterAction::class)->execute($chatter);

    expect(ChatSession::whereKey($chatterSession->id)->exists())->toBeFalse()
        ->and(ChatSession::whereKey($staffSession->id)->exists())->toBeTrue()
        ->and(Conversation::whereKey($staffConversation->id)->exists())->toBeTrue()
        ->and(User::whereKey($staff->id)->exists())->toBeTrue()
        ->and(ChatCache::isOwnedBy($staffConversation->external_id, $staffViewer, $workspace->external_id))->toBeTrue();
});

it('wipes the deleted chatter conversation attachment files on S3', function (): void {
    config(['cache.stores.chat' => ['driver' => 'array']]);
    Storage::fake('public');

    $workspace = User::factory()->workspace()->create();
    $chatter = Chatter::factory()->ofWorkspace($workspace)->create();
    $agent = Agent::factory()->create(['workspace_id' => $workspace->workspace()->id]);

    $session = ChatSession::create(['workspace_id' => $agent->workspace_id, 'agent_id' => $agent->id, 'chatter_id' => $chatter->id]);
    $conversation = Conversation::create([
        'chat_session_id' => $session->id,
        'workspace_id' => $agent->workspace_id,
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

    $chatter = Chatter::factory()->create();
    $other = Chatter::factory()->create();

    $photoPath = 'chatters/'.$chatter->external_id.'/profile-photos/avatar.jpg';
    $otherPath = 'chatters/'.$other->external_id.'/profile-photos/avatar.jpg';
    Storage::disk('assets')->put($photoPath, 'img');
    Storage::disk('assets')->put($otherPath, 'img');
    $chatter->update(['profile_image_path' => $photoPath]);

    Storage::disk('assets')->assertExists($photoPath);

    app(DeleteChatterAction::class)->execute($chatter);

    Storage::disk('assets')->assertMissing($photoPath);
    Storage::disk('assets')->assertExists($otherPath);
});

it('deletes leads captured from the chatter conversations and leaves other chatters leads intact', function (): void {
    config(['cache.stores.chat' => ['driver' => 'array']]);

    $workspace = User::factory()->workspace()->create();
    $chatter = Chatter::factory()->ofWorkspace($workspace)->create();
    $sibling = Chatter::factory()->ofWorkspace($workspace)->create();
    $agent = Agent::factory()->create(['workspace_id' => $workspace->workspace()->id]);

    $session = ChatSession::create(['workspace_id' => $agent->workspace_id, 'agent_id' => $agent->id, 'chatter_id' => $chatter->id]);
    $conversation = Conversation::create([
        'chat_session_id' => $session->id,
        'workspace_id' => $agent->workspace_id,
        'agent_id' => $agent->id,
        'source' => ConversationSource::HostedChat,
        'title' => 'Lead chat',
    ]);
    $lead = FormSubmission::factory()->forAgent($agent)->create(['chat_conversation_id' => $conversation->id]);

    // A sibling chatter's lead on a different conversation must survive.
    $siblingSession = ChatSession::create(['workspace_id' => $agent->workspace_id, 'agent_id' => $agent->id, 'chatter_id' => $sibling->id]);
    $siblingConversation = Conversation::create([
        'chat_session_id' => $siblingSession->id,
        'workspace_id' => $agent->workspace_id,
        'agent_id' => $agent->id,
        'source' => ConversationSource::HostedChat,
        'title' => 'Sibling chat',
    ]);
    $siblingLead = FormSubmission::factory()->forAgent($agent)->create(['chat_conversation_id' => $siblingConversation->id]);

    app(DeleteChatterAction::class)->execute($chatter);

    expect(FormSubmission::whereKey($lead->id)->exists())->toBeFalse()
        ->and(FormSubmission::whereKey($siblingLead->id)->exists())->toBeTrue();
});

it('deletes the chatter open invitations to the workspace agents, without case', function (): void {
    $workspace = User::factory()->workspace()->create();
    $agent = createAgent($workspace);
    $second = createAgent($workspace);
    $chatter = Chatter::factory()->grantedTo($agent)->create(['email' => 'gone@example.test']);

    $first = deleteChatterTestInvitation($agent, 'gone@example.test');
    $upperCase = deleteChatterTestInvitation($second, 'Gone@Example.TEST');

    app(DeleteChatterAction::class)->execute($chatter);

    expect(AgentChatterInvitation::whereKey($first->id)->exists())->toBeFalse()
        ->and(AgentChatterInvitation::whereKey($upperCase->id)->exists())->toBeFalse();
});

it('keeps invitations of other emails and of the same email in other workspaces', function (): void {
    $workspace = User::factory()->workspace()->create();
    $agent = createAgent($workspace);
    $chatter = Chatter::factory()->grantedTo($agent)->create(['email' => 'gone@example.test']);

    $otherWorkspace = User::factory()->workspace()->create();
    $otherAgent = createAgent($otherWorkspace);

    $otherEmail = deleteChatterTestInvitation($agent, 'stays@example.test');
    $otherWorkspaceInvite = deleteChatterTestInvitation($otherAgent, 'gone@example.test');

    app(DeleteChatterAction::class)->execute($chatter);

    expect(AgentChatterInvitation::whereKey($otherEmail->id)->exists())->toBeTrue()
        ->and(AgentChatterInvitation::whereKey($otherWorkspaceInvite->id)->exists())->toBeTrue();
});

it('deletes a token-only chatter without touching invitations', function (): void {
    $workspace = User::factory()->workspace()->create();
    $agent = createAgent($workspace);
    $chatter = Chatter::factory()->grantedTo($agent)->create(['email' => null]);
    $invitation = deleteChatterTestInvitation($agent, 'someone@example.test');

    app(DeleteChatterAction::class)->execute($chatter);

    expect(Chatter::whereKey($chatter->id)->exists())->toBeFalse()
        ->and(AgentChatterInvitation::whereKey($invitation->id)->exists())->toBeTrue();
});
