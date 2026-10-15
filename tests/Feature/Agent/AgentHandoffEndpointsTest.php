<?php

use App\Identity\Workspace\Models\User;
use Domain\Conversation\Enums\ConversationSource;
use Domain\Conversation\Enums\HandoffStatus;
use Domain\Conversation\Enums\MessageRole;
use Domain\Conversation\Events\HandedOff;
use Domain\Conversation\Events\MessagePosted;
use Domain\Conversation\Events\TypingStarted;
use Domain\Conversation\Models\Conversation;
use Domain\Conversation\Models\Session;
use Domain\Membership\Team\Services\TeamService;
use Illuminate\Support\Facades\Event;

/**
 * The dashboard side of a live chat: an operator takes a conversation over,
 * replies, types, reads, and hands it back. Agent access and the takeover
 * permission come from route middleware; replying, typing and handing back
 * need no permission of their own, only holding the conversation, which
 * takes the takeover permission. A conversation of another agent is refused.
 */
beforeEach(function (): void {
    Event::fake([HandedOff::class, MessagePosted::class, TypingStarted::class]);

    $this->owner = User::factory()->create();
    app(TeamService::class)->createDefaultTeam($this->owner);
    $this->owner->refresh();
    $this->agent = makeTeamAgent($this->owner, 'Support Bot');
});

function pendingHandoff(object $test, string $status = 'pending', ?int $heldBy = null): Conversation
{
    $session = Session::factory()->create(['agent_id' => $test->agent->id]);

    return Conversation::factory()->withoutTitle()->create([
        'chat_session_id' => $session->id,
        'agent_id' => $test->agent->id,
        'source' => ConversationSource::Widget,
        'handoff_status' => $status,
        'handoff_user_id' => $heldBy,
        'is_visible' => true,
    ]);
}

function handoffRoute(string $name, object $test, Conversation $conversation): string
{
    return route($name, ['agent' => $test->agent->external_id, 'conversation' => $conversation->external_id]);
}

it('lists the conversations waiting for an operator', function (): void {
    $waiting = pendingHandoff($this);
    pendingHandoff($this, 'ai');

    $this->actingAs($this->owner)
        ->getJson(route('agents.handoff.pending', ['agent' => $this->agent->external_id]))
        ->assertOk()
        ->assertJsonPath('count', 1)
        ->assertJsonPath('pending.0.conversation_id', $waiting->external_id);
});

it('takes a conversation over, replies, types and hands it back', function (): void {
    $conversation = pendingHandoff($this);

    $this->actingAs($this->owner)
        ->postJson(handoffRoute('agents.handoff.takeover', $this, $conversation))
        ->assertSuccessful()
        ->assertJsonPath('handoff_status', HandoffStatus::Human->value);

    expect($conversation->fresh()->handoff_status)->toBe(HandoffStatus::Human);

    $this->actingAs($this->owner)
        ->postJson(handoffRoute('agents.handoff.message', $this, $conversation), ['content' => 'Hi, I am here.'])
        ->assertSuccessful()
        ->assertJsonPath('success', true)
        ->assertJsonPath('message.content', 'Hi, I am here.')
        ->assertJsonPath('message.role', MessageRole::Operator->value);

    $this->actingAs($this->owner)
        ->postJson(handoffRoute('agents.handoff.typing', $this, $conversation), ['is_typing' => true])
        ->assertSuccessful();

    Event::assertDispatched(TypingStarted::class, fn (TypingStarted $event): bool => $event->typerType === 'agent');

    $this->actingAs($this->owner)
        ->postJson(handoffRoute('agents.handoff.return', $this, $conversation))
        ->assertSuccessful()
        ->assertJsonPath('handoff_status', HandoffStatus::Ai->value);

    expect($conversation->fresh()->handoff_status)->toBe(HandoffStatus::Ai);
});

it('refuses a reply from an operator who does not hold the conversation', function (): void {
    $conversation = pendingHandoff($this);

    $this->actingAs($this->owner)
        ->postJson(handoffRoute('agents.handoff.message', $this, $conversation), ['content' => 'Hello'])
        ->assertForbidden()
        ->assertJsonPath('message', 'You must take over this conversation before sending messages.');

    Event::assertNotDispatched(MessagePosted::class);
});

it('hides a conversation that belongs to another agent', function (): void {
    $otherAgent = makeTeamAgent($this->owner, 'Other Bot');
    $session = Session::factory()->create(['agent_id' => $otherAgent->id]);
    $foreign = Conversation::factory()->withoutTitle()->create([
        'chat_session_id' => $session->id,
        'agent_id' => $otherAgent->id,
        'source' => ConversationSource::Widget,
        'handoff_status' => 'pending',
    ]);

    $this->actingAs($this->owner)
        ->postJson(handoffRoute('agents.handoff.takeover', $this, $foreign))
        ->assertNotFound();

    $this->actingAs($this->owner)
        ->postJson(handoffRoute('agents.handoff.mark-read', $this, $foreign))
        ->assertNotFound();

    expect($foreign->fresh()->handoff_status)->toBe(HandoffStatus::Pending);
});

it('reports a conflict when another operator already holds the conversation', function (): void {
    $colleague = User::factory()->employee($this->owner)->create();
    $conversation = pendingHandoff($this, 'human', heldBy: $colleague->id);

    $this->actingAs($this->owner)
        ->postJson(handoffRoute('agents.handoff.takeover', $this, $conversation))
        ->assertStatus(409);
});

describe('teammates', function (): void {
    it('refuses the takeover to a teammate without agent.chat.takeover', function (): void {
        $teammate = teammateWithPermissions($this->owner, ['agent.chat.view']);
        $conversation = pendingHandoff($this);

        $this->actingAs($teammate)
            ->postJson(handoffRoute('agents.handoff.takeover', $this, $conversation))
            ->assertForbidden();

        expect($conversation->fresh())
            ->handoff_status->toBe(HandoffStatus::Pending)
            ->handoff_user_id->toBeNull();
        Event::assertNotDispatched(HandedOff::class);
    });

    it('lets a teammate with agent.chat.takeover take over, reply, type and hand back', function (): void {
        $teammate = teammateWithPermissions($this->owner, ['agent.chat.view', 'agent.chat.takeover']);
        $conversation = pendingHandoff($this);

        $this->actingAs($teammate)
            ->postJson(handoffRoute('agents.handoff.takeover', $this, $conversation))
            ->assertSuccessful();

        expect($conversation->fresh()->handoff_user_id)->toBe($teammate->id);

        $this->actingAs($teammate)
            ->postJson(handoffRoute('agents.handoff.message', $this, $conversation), ['content' => 'On it.'])
            ->assertSuccessful();
        $this->actingAs($teammate)
            ->postJson(handoffRoute('agents.handoff.typing', $this, $conversation), ['is_typing' => true])
            ->assertSuccessful();
        $this->actingAs($teammate)
            ->postJson(handoffRoute('agents.handoff.return', $this, $conversation))
            ->assertSuccessful();

        expect($conversation->fresh()->handoff_status)->toBe(HandoffStatus::Ai);
    });

    // The waiting list (visitor name, email and last message) and mark-read
    // carry no permission of their own today: agent access is enough.
    it('lets a teammate without permissions see the waiting list and mark it read, but not act on a conversation someone else holds', function (): void {
        $teammate = teammateWithPermissions($this->owner, []);
        $waiting = pendingHandoff($this);
        $held = pendingHandoff($this, 'human', heldBy: $this->owner->id);

        $this->actingAs($teammate)
            ->getJson(route('agents.handoff.pending', ['agent' => $this->agent->external_id]))
            ->assertOk()
            ->assertJsonPath('pending.0.conversation_id', $waiting->external_id);
        $this->actingAs($teammate)
            ->postJson(handoffRoute('agents.handoff.mark-read', $this, $waiting))
            ->assertSuccessful()
            ->assertJsonPath('marked_count', 0);

        $this->actingAs($teammate)
            ->postJson(handoffRoute('agents.handoff.message', $this, $held), ['content' => 'Hello'])
            ->assertForbidden();
        $this->actingAs($teammate)
            ->postJson(handoffRoute('agents.handoff.typing', $this, $held), ['is_typing' => true])
            ->assertForbidden();
        $this->actingAs($teammate)
            ->postJson(handoffRoute('agents.handoff.return', $this, $held))
            ->assertConflict();

        expect($held->fresh())
            ->handoff_status->toBe(HandoffStatus::Human)
            ->handoff_user_id->toBe($this->owner->id);
        Event::assertNotDispatched(MessagePosted::class);
    });
});
