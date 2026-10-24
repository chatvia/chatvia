<?php

use App\Identity\Chatter\Enums\ChatterSource;
use App\Identity\Chatter\Models\Chatter;
use App\Identity\Workspace\Models\User;
use Domain\Agent\Models\Agent;
use Domain\Membership\Agent\Actions\InviteAgentChatterAction;
use Domain\Membership\Agent\Data\AgentChatterInvitationData;
use Domain\Membership\Agent\Data\InviteAgentChatterData;
use Domain\Membership\Agent\Events\AgentChatterInvited;
use Domain\Membership\Agent\Exceptions\AgentChatterInvitationRejectedException;
use Domain\Membership\Agent\Models\AgentChatterInvitation;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * InviteAgentChatterAction is the FIRST line of defence in the agent-invitation
 * flow (AcceptAgentInvitationAction is the second). It only ever looks inside
 * the agent's own workspace: the same email in another workspace, or on a
 * staff account, is a different person, gets its own chatter row, and the
 * answer is the same as for a brand-new email, so it never reveals that the
 * email exists elsewhere. Its idempotency stops forwarded-link replay.
 */
function iacWorkspaceAndAgent(): array
{
    $workspace = User::factory()->create();

    $agent = Agent::factory()->create(['workspace_id' => $workspace->workspace()->id, 'is_public' => false]);

    return [$workspace, $agent];
}

function iacInvite(Agent $agent, string $email): AgentChatterInvitationData
{
    return app(InviteAgentChatterAction::class)->execute(new InviteAgentChatterData(
        agent: $agent,
        email: $email,
    ));
}

/** The chatters of the workspace with the email, compared without case. */
function iacWorkspaceChatters(User $workspace, string $email): Builder
{
    return Chatter::query()
        ->where('workspace_id', $workspace->workspace()->id)
        ->whereRaw('lower(email) = ?', [Str::lower($email)]);
}

it('provisions a new chatter, attaches the agent pivot, and dispatches AgentChatterInvited', function (): void {
    Event::fake([AgentChatterInvited::class]);
    [$workspace, $agent] = iacWorkspaceAndAgent();

    $result = iacInvite($agent, 'new@example.com');

    $chatter = iacWorkspaceChatters($workspace, 'new@example.com')->first();

    expect($chatter)->toBeInstanceOf(Chatter::class)
        ->and($chatter->workspace_id)->toBe($workspace->workspace()->id)
        ->and($chatter->source)->toBe(ChatterSource::Dashboard)
        ->and($chatter->email_verified_at)->toBeNull()
        ->and($chatter->password)->toBeNull()
        ->and($agent->chatters()->whereKey($chatter->id)->exists())->toBeTrue()
        ->and(AgentChatterInvitation::query()->where('agent_id', $agent->id)->where('email', 'new@example.com')->count())->toBe(1)
        ->and($result->email)->toBe('new@example.com');

    Event::assertDispatched(AgentChatterInvited::class);
});

it('stores the invited email in lower case', function (): void {
    Event::fake([AgentChatterInvited::class]);
    [$workspace, $agent] = iacWorkspaceAndAgent();

    $result = iacInvite($agent, '  New.Person@Example.COM ');

    expect($result->email)->toBe('new.person@example.com')
        ->and(iacWorkspaceChatters($workspace, 'new.person@example.com')->value('email'))->toBe('new.person@example.com');
});

it('rejects inviting to a public agent', function (): void {
    [$workspace, $agent] = iacWorkspaceAndAgent();
    $agent->update(['is_public' => true]);

    expect(fn (): AgentChatterInvitationData => iacInvite($agent, 'new@example.com'))
        ->toThrow(AgentChatterInvitationRejectedException::class);

    expect(iacWorkspaceChatters($workspace, 'new@example.com')->exists())->toBeFalse();
});

it('invites the email of a staff account as a separate chatter and leaves the staff account alone', function (): void {
    Event::fake([AgentChatterInvited::class]);
    [$workspace, $agent] = iacWorkspaceAndAgent();
    $staff = User::factory()->employee($workspace)->create(['email' => 'boss@example.com']);
    $staffPassword = $staff->password;

    $result = iacInvite($agent, 'boss@example.com');

    $chatter = iacWorkspaceChatters($workspace, 'boss@example.com')->first();

    expect($result->email)->toBe('boss@example.com')
        ->and($chatter)->toBeInstanceOf(Chatter::class)
        ->and($agent->chatters()->whereKey($chatter->id)->exists())->toBeTrue();

    $staff->refresh();
    expect($staff->isTeammate())->toBeTrue()
        ->and($staff->password)->toBe($staffPassword);

    Event::assertDispatched(AgentChatterInvited::class);
});

it('invites an email used by a chatter of another workspace as a new, separate chatter', function (): void {
    Event::fake([AgentChatterInvited::class]);
    [$workspace, $agent] = iacWorkspaceAndAgent();

    $otherWorkspace = User::factory()->create();
    $foreign = Chatter::factory()->ofWorkspace($otherWorkspace)->create([
        'email' => 'foreign@example.com',
        'password' => Hash::make('foreign-pass'),
    ]);

    $result = iacInvite($agent, 'foreign@example.com');

    $chatter = iacWorkspaceChatters($workspace, 'foreign@example.com')->first();

    expect($result->email)->toBe('foreign@example.com')
        ->and($chatter)->toBeInstanceOf(Chatter::class)
        ->and($chatter->id)->not->toBe($foreign->id)
        ->and($chatter->email_verified_at)->toBeNull()
        ->and($chatter->password)->toBeNull()
        ->and($agent->chatters()->whereKey($chatter->id)->exists())->toBeTrue()
        // The other workspace's chatter is untouched and gets no grant here.
        ->and($agent->chatters()->whereKey($foreign->id)->exists())->toBeFalse()
        ->and(Hash::check('foreign-pass', $foreign->fresh()->password))->toBeTrue()
        ->and($foreign->fresh()->workspace_id)->toBe($otherWorkspace->workspace()->id);
});

it('treats an email known elsewhere like a new one', function (): void {
    Event::fake([AgentChatterInvited::class]);
    [$workspace, $agent] = iacWorkspaceAndAgent();
    $foreign = Chatter::factory()->create(['email' => 'known@example.com']);
    $staff = User::factory()->workspace()->create(['email' => 'known-staff@example.com']);
    $foreignBefore = $foreign->only(['workspace_id', 'password', 'email_verified_at']);
    $staffBefore = $staff->only(['password', 'email_verified_at']);

    foreach (['unknown@example.com', 'known@example.com', 'known-staff@example.com'] as $email) {
        $result = iacInvite($agent, $email);
        $chatter = iacWorkspaceChatters($workspace, $email)->sole();

        // Each email gets a new, pending chatter of this workspace with the grant.
        expect($result->email)->toBe($email)
            ->and($chatter->id)->not->toBe($foreign->id)
            ->and($chatter->password)->toBeNull()
            ->and($chatter->email_verified_at)->toBeNull()
            ->and($agent->chatters()->whereKey($chatter->id)->exists())->toBeTrue();
    }

    expect($foreign->fresh()->only(['workspace_id', 'password', 'email_verified_at']))->toEqual($foreignBefore)
        ->and($staff->fresh()->only(['password', 'email_verified_at']))->toEqual($staffBefore);

    Event::assertDispatchedTimes(AgentChatterInvited::class, 3);
});

it('is idempotent: re-inviting the same email rotates the token and keeps one pending row + one chatter', function (): void {
    Event::fake([AgentChatterInvited::class]);
    [$workspace, $agent] = iacWorkspaceAndAgent();

    iacInvite($agent, 'repeat@example.com');
    $firstToken = AgentChatterInvitation::query()->where('agent_id', $agent->id)->where('email', 'repeat@example.com')->value('token');

    iacInvite($agent, 'Repeat@Example.com');

    $rows = AgentChatterInvitation::query()->where('agent_id', $agent->id)->where('email', 'repeat@example.com');

    expect($rows->count())->toBe(1)
        ->and($rows->value('token'))->not->toBe($firstToken)
        ->and(iacWorkspaceChatters($workspace, 'repeat@example.com')->count())->toBe(1);
});

it('reuses an existing chatter already under the same workspace, without case', function (): void {
    Event::fake([AgentChatterInvited::class]);
    [$workspace, $agent] = iacWorkspaceAndAgent();

    $existing = Chatter::factory()->ofWorkspace($workspace)->create(['email' => 'existing@example.com']);

    iacInvite($agent, 'EXISTING@example.com');

    expect(iacWorkspaceChatters($workspace, 'existing@example.com')->count())->toBe(1)
        ->and($agent->chatters()->whereKey($existing->id)->exists())->toBeTrue();
});
