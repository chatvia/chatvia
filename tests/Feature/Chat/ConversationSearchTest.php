<?php

use App\Identity\Chatter\Actions\CreateChatterAction;
use App\Identity\Chatter\Data\CreateChatterData;
use App\Identity\Workspace\Models\User;
use Domain\Agent\Models\Agent;
use Domain\Conversation\Enums\ConversationSource;
use Domain\Conversation\Models\Conversation;
use Domain\Conversation\Models\Session;
use Domain\Membership\Team\Services\TeamService;
use Illuminate\Support\Str;

/**
 * Conversation search is a read: it must never create a chat session, and it
 * returns nothing when the visitor has no session yet.
 */
beforeEach(function (): void {
    fakeMessageCreditGate();
});

it('returns no results and creates no session for a visitor without one', function (): void {
    $agent = Agent::factory()->create(['is_public' => true]);

    $this->getJson("https://{$agent->sub_domain}.chatvia.test/chat/conversations/search?q=hello")
        ->assertOk()
        ->assertExactJson([]);

    expect(Session::query()->where('agent_id', $agent->id)->count())->toBe(0);
});

it('finds a signed-in chatter\'s conversation by title in their existing session', function (): void {
    $owner = User::factory()->create();
    app(TeamService::class)->createDefaultTeam($owner);
    $agent = createAgent($owner, ['is_public' => false, 'sub_domain' => Str::lower(Str::random(12))]);
    $chatter = app(CreateChatterAction::class)->execute(new CreateChatterData($owner, $agent, 'Jane'));

    $session = Session::create(['agent_id' => $agent->id, 'user_id' => $chatter->id]);
    $conversation = Conversation::create([
        'chat_session_id' => $session->id,
        'agent_id' => $agent->id,
        'title' => 'Billing question',
        'is_visible' => true,
        'source' => ConversationSource::HostedChat,
    ]);

    $this->actingAs($chatter->fresh())
        ->getJson("https://{$agent->sub_domain}.chatvia.test/chat/conversations/search?q=billing")
        ->assertOk()
        ->assertJsonPath('0.id', $conversation->external_id);

    expect(Session::query()->where('agent_id', $agent->id)->count())->toBe(1);
});
