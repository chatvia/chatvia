<?php

use App\Identity\Chatter\Models\Chatter;
use App\Identity\Chatter\Services\ChatterAuthTokenService;
use App\Identity\Workspace\Models\User;
use Domain\Agent\Models\Agent;
use Domain\Conversation\Actions\Conversations\StartConversationAction;
use Domain\Conversation\Actions\Sessions\StartChatSessionAction;
use Domain\Conversation\Data\Memory\ChatScopeData;
use Domain\Conversation\Data\Memory\ChatViewerData;
use Domain\Conversation\Enums\ConversationSource;
use Domain\Conversation\Services\Memory\ChatCache;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

/**
 * Staff (owners, teammates, admins) who open an agent's hosted chat sign in on
 * the `chat_staff` guard, chatters on `chat`. Chatter ids and staff ids come
 * from two tables and can be equal, so everything the chat keys by viewer
 * carries the kind as well: an equal id never lets one open the other's
 * conversations.
 */
beforeEach(function (): void {
    config(['cache.stores.chat' => ['driver' => 'array']]);

    $this->owner = User::factory()->create();
    $this->agent = createAgent($this->owner, ['slug' => Str::lower(Str::random(12)), 'is_public' => false]);
});

/**
 * A staff account and a chatter of $agent's workspace that share one id. The
 * id is taken from both id sequences, so neither table can already hold it.
 *
 * @return array{0: User, 1: Chatter}
 */
function staffAndChatterWithOneId(Agent $agent): array
{
    $id = sharedStaffAndChatterId();

    $staff = User::factory()->employee($agent->workspace->owner)->create(['id' => $id]);
    $chatter = Chatter::factory()->grantedTo($agent)->create(['id' => $id]);

    return [$staff, $chatter];
}

it('signs a staff owner in on chat_staff from an auth_token and opens the private chat', function (): void {
    $token = app(ChatterAuthTokenService::class)->generateToken($this->agent, $this->owner, 5);

    $this->get("/chat/{$this->agent->slug}?auth_token={$token}")
        ->assertRedirect("/chat/{$this->agent->slug}");

    expect(Auth::guard('chat_staff')->id())->toBe($this->owner->id)
        ->and(Auth::guard('chat')->check())->toBeFalse()
        ->and(Auth::guard('web')->check())->toBeFalse();

    $this->get("/chat/{$this->agent->slug}")->assertOk();
});

it('keeps conversation ownership markers apart for a chatter and a staff account with the same id', function (): void {
    $workspace = $this->agent->workspaceExternalId();
    $conversationId = (string) Str::uuid();

    ChatCache::rememberOwner($conversationId, ChatViewerData::staff(42), $workspace);

    expect(ChatCache::isOwnedBy($conversationId, ChatViewerData::staff(42), $workspace))->toBeTrue()
        ->and(ChatCache::isOwnedBy($conversationId, ChatViewerData::chatter(42), $workspace))->toBeFalse()
        ->and(ChatCache::isOwnedBy($conversationId, null, $workspace))->toBeFalse()
        ->and(ChatCache::ownerKey(ChatViewerData::chatter(42), $conversationId))
        ->not->toBe(ChatCache::ownerKey(ChatViewerData::staff(42), $conversationId));
});

it('never lets a chatter open a staff member\'s conversation when their ids are equal', function (): void {
    [$staff, $chatter] = staffAndChatterWithOneId($this->agent);
    $viewer = ChatViewerData::staff($staff->id);

    $sessionId = app(StartChatSessionAction::class)->execute($this->agent, $viewer);
    $conversationId = app(StartConversationAction::class)->execute(
        new ChatScopeData($this->agent, $sessionId, viewer: $viewer),
        ConversationSource::HostedChat,
    );

    $url = "/chat/{$this->agent->slug}/conversations/{$conversationId}/messages";

    $this->actingAs($chatter, 'chat')->getJson($url)->assertForbidden();

    app('auth')->forgetGuards();

    $this->actingAs($staff, 'chat_staff')->getJson($url)->assertOk();
});
