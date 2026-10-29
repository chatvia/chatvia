<?php

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

/**
 * DeleteChatterAction: the chatter row and everything tied to it (sessions,
 * chat memory, attachment files, leads, avatar, open invitations) go; other
 * chatters, staff accounts and other workspaces keep theirs.
 */
beforeEach(function (): void {
    Storage::fake(Attachment::DISK);
    Storage::fake('assets');
});

/** An open invitation of the email to the agent. */
function dcaInvitation(Agent $agent, string $email): AgentChatterInvitation
{
    return AgentChatterInvitation::factory()->forAgent($agent)->create(['email' => $email]);
}

/** A hosted-chat session and conversation of the chatter, held in chat memory as the app writes it. */
function dcaChat(Chatter $chatter, Agent $agent): Conversation
{
    $session = ChatSession::factory()->forChatter($chatter)->create(['agent_id' => $agent->id]);
    $conversation = Conversation::factory()->create(['chat_session_id' => $session->id, 'source' => ConversationSource::HostedChat]);
    Cache::driver('chat')->tags(ChatCache::sessionTags($agent->workspaceExternalId(), $agent->external_id, $session->external_id))->forever($session->external_id, ['conversations' => []]);
    ChatCache::rememberOwner($conversation->external_id, ChatViewerData::chatter($chatter->id), $agent);

    return $conversation;
}

function dcaSessionCached(Agent $agent, Conversation $conversation): bool
{
    $session = $conversation->session;

    return Cache::driver('chat')->tags(ChatCache::sessionTags($agent->workspaceExternalId(), $agent->external_id, $session->external_id))->has($session->external_id);
}

it('erases the chatter with its grants, sessions, chat memory, files and leads, and leaves a sibling chatter\'s alone', function (): void {
    $workspace = User::factory()->workspace()->create();
    $agent = createAgent($workspace);
    $chatter = Chatter::factory()->grantedTo($agent)->create();
    $sibling = Chatter::factory()->grantedTo($agent)->create();
    $conversation = dcaChat($chatter, $agent);
    $siblingConversation = dcaChat($sibling, $agent);
    $attachment = Attachment::factory()->forConversation($conversation)->stored()->create();
    $media = GeneratedMedia::factory()->forConversation($conversation)->stored()->create();
    $speech = ChatStoragePath::forConversation($agent, $conversation->external_id, 'speech').'/m1-abc.mp3';
    Storage::disk(Attachment::DISK)->put($speech, 'mp3');
    $lead = FormSubmission::factory()->forAgent($agent)->create(['chat_conversation_id' => $conversation->id]);
    $siblingLead = FormSubmission::factory()->forAgent($agent)->create(['chat_conversation_id' => $siblingConversation->id]);
    $photoPath = $chatter->storageRoot().'/profile-photos/avatar.jpg';
    $siblingPhotoPath = $sibling->storageRoot().'/profile-photos/avatar.jpg';
    Storage::disk('assets')->put($photoPath, 'img');
    Storage::disk('assets')->put($siblingPhotoPath, 'img');
    $chatter->update(['profile_image_path' => $photoPath]);
    $conversationSession = $conversation->session;

    app(DeleteChatterAction::class)->execute($chatter);

    expect(Chatter::query()->whereKey($chatter->id)->exists())->toBeFalse()
        ->and(DB::table('agent_chatter')->where('chatter_id', $chatter->id)->exists())->toBeFalse()
        ->and(ChatSession::query()->whereKey($conversationSession->id)->exists())->toBeFalse()
        ->and(dcaSessionCached($agent, $siblingConversation))->toBeTrue()
        ->and(ChatCache::isOwnedBy($conversation->external_id, ChatViewerData::chatter($chatter->id), $agent))->toBeFalse()
        ->and(Attachment::query()->whereKey($attachment->id)->exists())->toBeFalse()
        ->and(GeneratedMedia::query()->whereKey($media->id)->exists())->toBeFalse()
        ->and(FormSubmission::query()->whereKey($lead->id)->exists())->toBeFalse()
        ->and(Chatter::query()->whereKey($sibling->id)->exists())->toBeTrue()
        ->and(DB::table('agent_chatter')->where('chatter_id', $sibling->id)->where('agent_id', $agent->id)->exists())->toBeTrue()
        ->and(ChatSession::query()->whereKey($siblingConversation->chat_session_id)->exists())->toBeTrue()
        ->and(FormSubmission::query()->whereKey($siblingLead->id)->exists())->toBeTrue()
        ->and(Agent::query()->whereKey($agent->id)->exists())->toBeTrue()
        ->and(User::query()->whereKey($workspace->id)->exists())->toBeTrue()
        ->and(Cache::driver('chat')->tags(ChatCache::sessionTags($agent->workspaceExternalId(), $agent->external_id, $conversationSession->external_id))->has($conversationSession->external_id))->toBeFalse();
    Storage::disk(Attachment::DISK)->assertMissing($attachment->filepath);
    Storage::disk(Attachment::DISK)->assertMissing($speech);
    Storage::disk(GeneratedMedia::DISK)->assertMissing($media->filepath);
    Storage::disk('assets')->assertMissing($photoPath);
    Storage::disk('assets')->assertExists($siblingPhotoPath);
});

it('keeps the sessions of a staff account whose id equals the chatter id', function (): void {
    $workspace = User::factory()->workspace()->create();
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

    expect(ChatSession::query()->whereKey($chatterSession->id)->exists())->toBeFalse()
        ->and(ChatSession::query()->whereKey($staffSession->id)->exists())->toBeTrue()
        ->and(Conversation::query()->whereKey($staffConversation->id)->exists())->toBeTrue()
        ->and(User::query()->whereKey($staff->id)->exists())->toBeTrue()
        ->and(ChatCache::isOwnedBy($staffConversation->external_id, $staffViewer, $agent))->toBeTrue();
});

it('deletes the chatter\'s open invitations without case and keeps other emails\' and other workspaces\'', function (): void {
    $workspace = User::factory()->workspace()->create();
    $agent = createAgent($workspace);
    $second = createAgent($workspace);
    $chatter = Chatter::factory()->grantedTo($agent)->create(['email' => 'gone@example.test']);
    $tokenOnly = Chatter::factory()->grantedTo($agent)->create(['email' => null]);
    $first = dcaInvitation($agent, 'gone@example.test');
    $upperCase = dcaInvitation($second, 'Gone@Example.TEST');
    $otherEmail = dcaInvitation($agent, 'stays@example.test');
    $otherWorkspaceInvite = dcaInvitation(createAgent(User::factory()->workspace()->create()), 'gone@example.test');

    app(DeleteChatterAction::class)->execute($chatter);
    app(DeleteChatterAction::class)->execute($tokenOnly);

    expect(AgentChatterInvitation::query()->whereKey($first->id)->exists())->toBeFalse()
        ->and(AgentChatterInvitation::query()->whereKey($upperCase->id)->exists())->toBeFalse()
        ->and(AgentChatterInvitation::query()->whereKey($otherEmail->id)->exists())->toBeTrue()
        ->and(AgentChatterInvitation::query()->whereKey($otherWorkspaceInvite->id)->exists())->toBeTrue()
        ->and(Chatter::query()->whereKey($tokenOnly->id)->exists())->toBeFalse();
});
