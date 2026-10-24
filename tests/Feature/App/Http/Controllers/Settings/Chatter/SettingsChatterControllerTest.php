<?php

/**
 * Workspace Members page, the chatters (SettingsChatterController): inviting a
 * chatter with the agents they may use, and a chatter's access controls (the
 * monthly message cap on their own `message_limits.chatter_id` row and the
 * deactivation switch). The chatter is created inside the inviting workspace
 * only; an email a chatter of another workspace uses is invited like any new
 * email, and the answer never hints that it exists elsewhere.
 */

use App\Identity\Chatter\Models\Chatter;
use App\Identity\Workspace\Models\MessageLimit;
use App\Identity\Workspace\Models\User;
use Domain\Agent\Models\Agent;
use Domain\Membership\Agent\Events\AgentChatterInvited;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;

beforeEach(function (): void {
    $this->owner = User::factory()->create();
    ownerTeam($this->owner);
    $this->agent = Agent::factory()->create(['workspace_id' => $this->owner->workspace()->id, 'is_public' => false]);
});

describe('store', function (): void {
    it('invites a chatter and grants the selected agent', function (): void {
        Event::fake([AgentChatterInvited::class]);

        $this->actingAs($this->owner)
            ->post(route('people.chatters.invite'), [
                'email' => 'newbie@example.com',
                'agents' => [$this->agent->external_id],
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors()
            ->assertSessionHas('invitation.email', 'newbie@example.com');

        $chatter = $this->owner->workspace()->chatters()->where('email', 'newbie@example.com')->sole();

        expect($this->agent->chatters()->whereKey($chatter->id)->exists())->toBeTrue()
            ->and(User::query()->where('email', 'newbie@example.com')->exists())->toBeFalse();

        Event::assertDispatched(AgentChatterInvited::class);
    });

    it('invites an email that is a chatter of another workspace as a separate chatter, without saying so', function (): void {
        Event::fake([AgentChatterInvited::class]);

        $otherAgent = Agent::factory()->create(['is_public' => false]);
        $foreign = Chatter::factory()->grantedTo($otherAgent)->create([
            'email' => 'shared@example.com',
            'password' => Hash::make('foreign-pass'),
        ]);

        $this->actingAs($this->owner)
            ->post(route('people.chatters.invite'), [
                'email' => 'Shared@Example.com',
                'agents' => [$this->agent->external_id],
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors()
            ->assertSessionHas('invitation.email', 'shared@example.com');

        // The flash is the same shape as for a brand-new email.
        expect(array_keys(session('invitation')))->toBe(['email', 'accept_url', 'expires_at']);

        $chatter = $this->owner->workspace()->chatters()->where('email', 'shared@example.com')->sole();

        expect($chatter->id)->not->toBe($foreign->id)
            ->and($this->agent->chatters()->whereKey($chatter->id)->exists())->toBeTrue()
            ->and($this->agent->chatters()->whereKey($foreign->id)->exists())->toBeFalse()
            ->and(Hash::check('foreign-pass', $foreign->fresh()->password))->toBeTrue();
    });

    it('refuses an invitation without an agent the workspace may invite to', function (string $agents): void {
        $chosen = match ($agents) {
            'none' => [],
            'public' => [Agent::factory()->create(['workspace_id' => $this->owner->workspace()->id, 'is_public' => true])->external_id],
            'foreign' => [Agent::factory()->create(['is_public' => false])->external_id],
        };

        $this->actingAs($this->owner)
            ->post(route('people.chatters.invite'), ['email' => 'newbie@example.com', 'agents' => $chosen])
            ->assertSessionHasErrors('agents');

        expect(Chatter::query()->where('email', 'newbie@example.com')->exists())->toBeFalse();
    })->with([
        'no agent' => 'none',
        'a public agent' => 'public',
        "another workspace's agent" => 'foreign',
    ]);
});

describe('update', function (): void {
    beforeEach(function (): void {
        $this->chatter = Chatter::factory()->ofWorkspace($this->owner)->create(['name' => 'Casey']);
    });

    it('sets a chatter\'s message limit and deactivation and returns the refreshed row', function (): void {
        $this->actingAs($this->owner)
            ->patchJson(route('people.chatters.update', ['chatter' => $this->chatter->external_id]), [
                'messages_limit' => 500,
                'deactivated' => true,
                'deactivation_reason' => 'Abuse',
            ])
            ->assertOk()
            ->assertJsonPath('messages_limit', 500)
            ->assertJsonPath('messages_used', 0)
            ->assertJsonPath('is_deactivated', true)
            ->assertJsonPath('deactivation_reason', 'Abuse');

        expect(MessageLimit::query()->where('chatter_id', $this->chatter->id)->value('monthly_limit'))->toBe(500)
            // The workspace's own limit is a different row and stays unset.
            ->and(MessageLimit::query()->where('workspace_id', $this->owner->workspace()->id)->exists())->toBeFalse()
            ->and($this->chatter->fresh()->isDeactivated())->toBeTrue()
            ->and($this->owner->fresh()->isDeactivated())->toBeFalse();
    });

    it('404s for a teammate external id: only chatters resolve', function (): void {
        $teammate = User::factory()->employee($this->owner)->create();

        $this->actingAs($this->owner)
            ->patchJson(route('people.chatters.update', ['chatter' => $teammate->external_id]), [
                'messages_limit' => null,
                'deactivated' => true,
            ])
            ->assertNotFound();

        expect($teammate->fresh()->isDeactivated())->toBeFalse();
    });

    it('validates the message limit is a positive integer', function (): void {
        $this->actingAs($this->owner)
            ->patchJson(route('people.chatters.update', ['chatter' => $this->chatter->external_id]), [
                'messages_limit' => 0,
                'deactivated' => false,
            ])
            ->assertJsonValidationErrors('messages_limit');
    });
});
