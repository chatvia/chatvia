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
use Domain\Conversation\Models\GeneratedMedia;
use Domain\Conversation\Models\Session as ChatSession;
use Domain\Conversation\Services\ChatStoragePath;
use Domain\Conversation\Services\Memory\ChatCache;
use Domain\Form\Models\FormSubmission;
use Domain\Membership\Agent\Models\AgentChatterInvitation;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    // Chatter deletion wipes the avatar tree on the assets disk and attachment
    // files on the attachments disk.
    Storage::fake(Attachment::DISK);
    Storage::fake('assets');
});

/** An open invitation of the email to the agent. */
function dcaInvitation(Agent $agent, string $email): AgentChatterInvitation
{
    return AgentChatterInvitation::factory()->forAgent($agent)->create(['email' => $email]);
}

it('deletes the chatter and its agent grants, and leaves the other chatters of the workspace alone', function (): void {
    $workspace = User::factory()->workspace()->create();
    $agent = createAgent($workspace);
    $chatter = Chatter::factory()->grantedTo($agent)->create();
    $sibling = Chatter::factory()->grantedTo($agent)->create();

    app(DeleteChatterAction::class)->execute($chatter);

    expect(Chatter::whereKey($chatter->id)->exists())->toBeFalse()
        ->and(DB::table('agent_chatter')->where('chatter_id', $chatter->id)->exists())->toBeFalse()
        ->and(Chatter::whereKey($sibling->id)->exists())->toBeTrue()
        ->and(DB::table('agent_chatter')->where('chatter_id', $sibling->id)->where('agent_id', $agent->id)->exists())->toBeTrue()
        ->and(Agent::whereKey($agent->id)->exists())->toBeTrue()
        ->and(User::whereKey($workspace->id)->exists())->toBeTrue();
});

it('flushes the deleted chatter chat memory (session + ownership marker) and leaves siblings intact', function (): void {
    $workspace = User::factory()->workspace()->create();
    $chatter = Chatter::factory()->ofWorkspace($workspace)->create();
    $sibling = Chatter::factory()->ofWorkspace($workspace)->create();
    $agent = Agent::factory()->create(['workspace_id' => $workspace->workspace()->id]);
    $viewer = ChatViewerData::chatter($chatter->id);

    // The chatter's session + conversation, seeded exactly as the app writes:
    // session-data under [workspace, agent, session]; ownership marker (forever)
    // under [conversation, workspace, agent].
    $session = ChatSession::factory()->forChatter($chatter)->create(['agent_id' => $agent->id]);
    $conversation = Conversation::factory()->withTitle('Chatter chat')->create(['chat_session_id' => $session->id, 'source' => ConversationSource::HostedChat]);
    Cache::driver('chat')->tags(ChatCache::sessionTags($workspace->external_id, $agent->external_id, $session->external_id))->forever($session->external_id, ['conversations' => []]);
    ChatCache::rememberOwner($conversation->external_id, $viewer, $agent);

    // A sibling's session in the same workspace must survive.
    $siblingSession = ChatSession::factory()->forChatter($sibling)->create(['agent_id' => $agent->id]);
    Cache::driver('chat')->tags(ChatCache::sessionTags($workspace->external_id, $agent->external_id, $siblingSession->external_id))->forever($siblingSession->external_id, ['conversations' => []]);

    expect(ChatCache::isOwnedBy($conversation->external_id, $viewer, $agent))->toBeTrue();

    app(DeleteChatterAction::class)->execute($chatter);

    expect(Cache::driver('chat')->tags(ChatCache::sessionTags($workspace->external_id, $agent->external_id, $session->external_id))->has($session->external_id))->toBeFalse()
        ->and(ChatCache::isOwnedBy($conversation->external_id, $viewer, $agent))->toBeFalse()
        ->and(Cache::driver('chat')->tags(ChatCache::sessionTags($workspace->external_id, $agent->external_id, $siblingSession->external_id))->has($siblingSession->external_id))->toBeTrue()
        ->and(ChatSession::whereKey($session->id)->exists())->toBeFalse()
        ->and(ChatSession::whereKey($siblingSession->id)->exists())->toBeTrue();
});

it('keeps the sessions of a staff account whose id equals the chatter id', function (): void {
    $workspace = User::factory()->workspace()->create();
    // Chatter and staff ids come from different tables and can be equal.
    $sharedId = sharedStaffAndChatterId();
    $staff = User::factory()->employee($workspace)->create(['id' => $sharedId]);
    $chatter = Chatter::factory()->ofWorkspace($workspace)->create(['id' => $sharedId]);
    $agent = Agent::factory()->create(['workspace_id' => $workspace->workspace()->id]);

    $chatterSession = ChatSession::factory()->forChatter($chatter)->create(['agent_id' => $agent->id]);
    $staffSession = ChatSession::factory()->create(['agent_id' => $agent->id, 'user_id' => $staff->id]);
    $staffConversation = Conversation::factory()->withTitle('Staff preview')->create(['chat_session_id' => $staffSession->id, 'source' => ConversationSource::HostedChat]);
    $staffViewer = ChatViewerData::staff($staff->id);
    ChatCache::rememberOwner($staffConversation->external_id, $staffViewer, $agent);

    app(DeleteChatterAction::class)->execute($chatter);

    expect(ChatSession::whereKey($chatterSession->id)->exists())->toBeFalse()
        ->and(ChatSession::whereKey($staffSession->id)->exists())->toBeTrue()
        ->and(Conversation::whereKey($staffConversation->id)->exists())->toBeTrue()
        ->and(User::whereKey($staff->id)->exists())->toBeTrue()
        ->and(ChatCache::isOwnedBy($staffConversation->external_id, $staffViewer, $agent))->toBeTrue();
});

it('wipes the attachment files of the deleted chatter\'s conversations', function (): void {
    $workspace = User::factory()->workspace()->create();
    $chatter = Chatter::factory()->ofWorkspace($workspace)->create();
    $agent = Agent::factory()->create(['workspace_id' => $workspace->workspace()->id]);

    $session = ChatSession::factory()->forChatter($chatter)->create(['agent_id' => $agent->id]);
    $conversation = Conversation::factory()->withTitle('With attachment')->create(['chat_session_id' => $session->id, 'source' => ConversationSource::HostedChat]);
    $attachment = Attachment::factory()->forConversation($conversation)->stored()->create();

    app(DeleteChatterAction::class)->execute($chatter);

    Storage::disk(Attachment::DISK)->assertMissing($attachment->filepath);
    expect(Attachment::whereKey($attachment->id)->exists())->toBeFalse();
});

it('wipes the replies read aloud in the deleted chatter\'s conversations', function (): void {
    $workspace = User::factory()->workspace()->create();
    $chatter = Chatter::factory()->ofWorkspace($workspace)->create();
    $agent = Agent::factory()->create(['workspace_id' => $workspace->workspace()->id]);

    $session = ChatSession::factory()->forChatter($chatter)->create(['agent_id' => $agent->id]);
    $conversation = Conversation::factory()->create(['chat_session_id' => $session->id, 'source' => ConversationSource::HostedChat]);
    $speech = ChatStoragePath::forConversation($agent, $conversation->external_id, 'speech').'/m1-abc.mp3';
    Storage::disk(Attachment::DISK)->put($speech, 'mp3');

    app(DeleteChatterAction::class)->execute($chatter);

    Storage::disk(Attachment::DISK)->assertMissing($speech);
});

it('wipes the images and audio made in the deleted chatter\'s conversations', function (): void {
    $workspace = User::factory()->workspace()->create();
    $chatter = Chatter::factory()->ofWorkspace($workspace)->create();
    $agent = Agent::factory()->create(['workspace_id' => $workspace->workspace()->id]);

    $session = ChatSession::factory()->forChatter($chatter)->create(['agent_id' => $agent->id]);
    $conversation = Conversation::factory()->create(['chat_session_id' => $session->id, 'source' => ConversationSource::HostedChat]);
    $media = GeneratedMedia::factory()->forConversation($conversation)->stored()->create();

    app(DeleteChatterAction::class)->execute($chatter);

    Storage::disk(GeneratedMedia::DISK)->assertMissing($media->filepath);
    expect(GeneratedMedia::whereKey($media->id)->exists())->toBeFalse();
});

it('wipes the deleted chatter\'s profile photo and keeps other chatters\' photos', function (): void {
    $chatter = Chatter::factory()->create();
    $other = Chatter::factory()->create();

    $photoPath = $chatter->storageRoot().'/profile-photos/avatar.jpg';
    $otherPath = $other->storageRoot().'/profile-photos/avatar.jpg';
    Storage::disk('assets')->put($photoPath, 'img');
    Storage::disk('assets')->put($otherPath, 'img');
    $chatter->update(['profile_image_path' => $photoPath]);

    Storage::disk('assets')->assertExists($photoPath);

    app(DeleteChatterAction::class)->execute($chatter);

    Storage::disk('assets')->assertMissing($photoPath);
    Storage::disk('assets')->assertExists($otherPath);
});

it('deletes leads captured from the chatter conversations and leaves other chatters leads intact', function (): void {
    $workspace = User::factory()->workspace()->create();
    $chatter = Chatter::factory()->ofWorkspace($workspace)->create();
    $sibling = Chatter::factory()->ofWorkspace($workspace)->create();
    $agent = Agent::factory()->create(['workspace_id' => $workspace->workspace()->id]);

    $session = ChatSession::factory()->forChatter($chatter)->create(['agent_id' => $agent->id]);
    $conversation = Conversation::factory()->withTitle('Lead chat')->create(['chat_session_id' => $session->id, 'source' => ConversationSource::HostedChat]);
    $lead = FormSubmission::factory()->forAgent($agent)->create(['chat_conversation_id' => $conversation->id]);

    // A sibling chatter's lead on a different conversation must survive.
    $siblingSession = ChatSession::factory()->forChatter($sibling)->create(['agent_id' => $agent->id]);
    $siblingConversation = Conversation::factory()->withTitle('Sibling chat')->create(['chat_session_id' => $siblingSession->id, 'source' => ConversationSource::HostedChat]);
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

    $first = dcaInvitation($agent, 'gone@example.test');
    $upperCase = dcaInvitation($second, 'Gone@Example.TEST');

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

    $otherEmail = dcaInvitation($agent, 'stays@example.test');
    $otherWorkspaceInvite = dcaInvitation($otherAgent, 'gone@example.test');

    app(DeleteChatterAction::class)->execute($chatter);

    expect(AgentChatterInvitation::whereKey($otherEmail->id)->exists())->toBeTrue()
        ->and(AgentChatterInvitation::whereKey($otherWorkspaceInvite->id)->exists())->toBeTrue();
});

it('deletes a token-only chatter without touching invitations', function (): void {
    $workspace = User::factory()->workspace()->create();
    $agent = createAgent($workspace);
    $chatter = Chatter::factory()->grantedTo($agent)->create(['email' => null]);
    $invitation = dcaInvitation($agent, 'someone@example.test');

    app(DeleteChatterAction::class)->execute($chatter);

    expect(Chatter::whereKey($chatter->id)->exists())->toBeFalse()
        ->and(AgentChatterInvitation::whereKey($invitation->id)->exists())->toBeTrue();
});
