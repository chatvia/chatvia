<?php

use App\Identity\Workspace\Models\User;
use Domain\Conversation\Enums\HandoffStatus;
use Domain\Conversation\Models\Conversation;

/**
 * The Inbox list as JSON: who is chatting right now across the agents a
 * person may view, with a flag for the chats they may join. Another
 * workspace's chats never show; joining stays Chat Widget only and needs a
 * license key, though the person holding a chat keeps it. Which agents
 * a teammate sees and where they may reply are the Inbox agents' rules; the
 * order of the list is the Inbox query's.
 */
beforeEach(function (): void {
    withLicense();
    $this->owner = User::factory()->create();
    $this->support = makeTeamAgent($this->owner, 'Support');
});

it('answers each chat with its agent, its online state and whether the person may join', function (): void {
    $chat = Conversation::factory()->forAgent($this->support)->live()->create();

    $this->actingAs($this->owner)
        ->getJson(route('inbox.conversations'))
        ->assertOk()
        ->assertJsonPath('waiting_count', 0)
        ->assertJsonPath('conversations.0.conversation_id', $chat->external_id)
        ->assertJsonPath('conversations.0.agent.id', $this->support->external_id)
        ->assertJsonPath('conversations.0.is_online', true)
        ->assertJsonPath('conversations.0.can_join', true);
});

it('never shows another workspace\'s chats', function (): void {
    $stranger = User::factory()->create();
    Conversation::factory()->forAgent(makeTeamAgent($stranger, 'Theirs'))->live()->handedOff(HandoffStatus::Pending)->create();

    $this->actingAs($this->owner)
        ->getJson(route('inbox.conversations'))
        ->assertOk()
        ->assertJsonCount(0, 'conversations')
        ->assertJsonPath('waiting_count', 0);
});

it('lets nobody join a chat outside the Chat Widget', function (): void {
    Conversation::factory()->forAgent($this->support)->hostedChat()->live()->create();

    $this->actingAs($this->owner)
        ->getJson(route('inbox.conversations'))
        ->assertOk()
        ->assertJsonPath('conversations.0.can_join', false);
});

it('names the colleague who holds a chat to everyone but the holder', function (): void {
    $colleague = teammateWithPermissions($this->owner, ['agent.chat.view', 'agent.chat.takeover']);
    Conversation::factory()->forAgent($this->support)->live()->handedOff(HandoffStatus::Human, $colleague)->create();

    $this->actingAs($this->owner)
        ->getJson(route('inbox.conversations'))
        ->assertOk()
        ->assertJsonPath('conversations.0.holder_name', $colleague->name)
        ->assertJsonPath('conversations.0.held_by_me', false);

    $this->actingAs($colleague)
        ->getJson(route('inbox.conversations'))
        ->assertOk()
        ->assertJsonPath('conversations.0.held_by_me', true)
        ->assertJsonPath('conversations.0.holder_name', null)
        ->assertJsonPath('conversations.0.can_join', true);
});

it('lets nobody join without a license key, but the holder keeps their chat', function (): void {
    withoutLicense();
    $colleague = teammateWithPermissions($this->owner, ['agent.chat.view', 'agent.chat.takeover']);
    $held = Conversation::factory()->forAgent($this->support)->live()->handedOff(HandoffStatus::Human, $colleague)->create();
    $open = Conversation::factory()->forAgent($this->support)->live()->create();

    $joinable = fn (User $user): array => collect($this->actingAs($user)->getJson(route('inbox.conversations'))->assertOk()->json('conversations'))
        ->pluck('can_join', 'conversation_id')
        ->all();

    expect($joinable($this->owner))->toMatchArray([$held->external_id => false, $open->external_id => false])
        ->and($joinable($colleague))->toMatchArray([$held->external_id => true, $open->external_id => false]);
});
