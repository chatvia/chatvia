<?php

use App\Models\Agent;
use App\Models\Member;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

describe('Member Login', function () {
    it('allows member to access agent with valid credentials', function () {
        $user = User::factory()->create();
        $agent = Agent::factory()->create(['user_id' => $user->id]);

        $member = Member::factory()->create([
            'agent_id' => $agent->id,
            'email' => 'member@example.com',
            'password' => Hash::make('password'),
            'is_active' => true,
        ]);

        // Assuming there's a member login route (adjust if different)
        $response = $this->post(route('member.login'), [
            'email' => 'member@example.com',
            'password' => 'password',
            'agent_id' => $agent->id,
        ]);

        $response->assertSuccessful();
    });

    it('rejects inactive member login', function () {
        $user = User::factory()->create();
        $agent = Agent::factory()->create(['user_id' => $user->id]);

        $member = Member::factory()->create([
            'agent_id' => $agent->id,
            'email' => 'member@example.com',
            'password' => Hash::make('password'),
            'is_active' => false,
        ]);

        $response = $this->post(route('member.login'), [
            'email' => 'member@example.com',
            'password' => 'password',
            'agent_id' => $agent->id,
        ]);

        $response->assertStatus(403);
    });

    it('rejects member login with wrong password', function () {
        $user = User::factory()->create();
        $agent = Agent::factory()->create(['user_id' => $user->id]);

        $member = Member::factory()->create([
            'agent_id' => $agent->id,
            'email' => 'member@example.com',
            'password' => Hash::make('correct-password'),
            'is_active' => true,
        ]);

        $response = $this->post(route('member.login'), [
            'email' => 'member@example.com',
            'password' => 'wrong-password',
            'agent_id' => $agent->id,
        ]);

        $response->assertStatus(401);
    });

    it('rejects member accessing wrong agent', function () {
        $user = User::factory()->create();
        $agent1 = Agent::factory()->create(['user_id' => $user->id]);
        $agent2 = Agent::factory()->create(['user_id' => $user->id]);

        $member = Member::factory()->create([
            'agent_id' => $agent1->id,
            'email' => 'member@example.com',
            'password' => Hash::make('password'),
            'is_active' => true,
        ]);

        $response = $this->post(route('member.login'), [
            'email' => 'member@example.com',
            'password' => 'password',
            'agent_id' => $agent2->id, // Wrong agent
        ]);

        $response->assertStatus(403);
    });
});

describe('Member Password Management', function () {
    it('allows member to set password on first login', function () {
        $user = User::factory()->create();
        $agent = Agent::factory()->create(['user_id' => $user->id]);

        $member = Member::factory()->create([
            'agent_id' => $agent->id,
            'email' => 'member@example.com',
            'password' => null, // No password set
            'is_active' => true,
            'invitation_token' => 'valid-token',
        ]);

        $response = $this->post(route('member.password.set'), [
            'token' => 'valid-token',
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ]);

        $response->assertSuccessful();

        expect(Hash::check('new-password', $member->fresh()->password))->toBeTrue();
    });

    it('rejects password set with invalid token', function () {
        $user = User::factory()->create();
        $agent = Agent::factory()->create(['user_id' => $user->id]);

        $member = Member::factory()->create([
            'agent_id' => $agent->id,
            'email' => 'member@example.com',
            'password' => null,
            'is_active' => true,
            'invitation_token' => 'valid-token',
        ]);

        $response = $this->post(route('member.password.set'), [
            'token' => 'invalid-token',
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ]);

        $response->assertStatus(403);

        expect($member->fresh()->password)->toBeNull();
    });

    it('allows member to change password', function () {
        $user = User::factory()->create();
        $agent = Agent::factory()->create(['user_id' => $user->id]);

        $member = Member::factory()->create([
            'agent_id' => $agent->id,
            'email' => 'member@example.com',
            'password' => Hash::make('old-password'),
            'is_active' => true,
        ]);

        // Assuming member authentication
        $response = $this->actingAs($member, 'member')->post(route('member.password.change'), [
            'current_password' => 'old-password',
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ]);

        $response->assertSuccessful();

        expect(Hash::check('new-password', $member->fresh()->password))->toBeTrue();
        expect(Hash::check('old-password', $member->fresh()->password))->toBeFalse();
    });

    it('rejects password change with incorrect current password', function () {
        $user = User::factory()->create();
        $agent = Agent::factory()->create(['user_id' => $user->id]);

        $member = Member::factory()->create([
            'agent_id' => $agent->id,
            'email' => 'member@example.com',
            'password' => Hash::make('correct-password'),
            'is_active' => true,
        ]);

        $response = $this->actingAs($member, 'member')->post(route('member.password.change'), [
            'current_password' => 'wrong-password',
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ]);

        $response->assertSessionHasErrors('current_password');

        expect(Hash::check('correct-password', $member->fresh()->password))->toBeTrue();
    });

    it('requires password confirmation to match', function () {
        $user = User::factory()->create();
        $agent = Agent::factory()->create(['user_id' => $user->id]);

        $member = Member::factory()->create([
            'agent_id' => $agent->id,
            'email' => 'member@example.com',
            'password' => Hash::make('old-password'),
            'is_active' => true,
        ]);

        $response = $this->actingAs($member, 'member')->post(route('member.password.change'), [
            'current_password' => 'old-password',
            'password' => 'new-password',
            'password_confirmation' => 'different-password',
        ]);

        $response->assertSessionHasErrors('password');
    });
});

describe('Member Authorization', function () {
    it('allows member to access assigned agent', function () {
        $user = User::factory()->create();
        $agent = Agent::factory()->create(['user_id' => $user->id]);

        $member = Member::factory()->create([
            'agent_id' => $agent->id,
            'is_active' => true,
        ]);

        expect($member->hasAccessToAgent($agent))->toBeTrue();
    });

    it('denies member access to non-assigned agent', function () {
        $user = User::factory()->create();
        $agent1 = Agent::factory()->create(['user_id' => $user->id]);
        $agent2 = Agent::factory()->create(['user_id' => $user->id]);

        $member = Member::factory()->create([
            'agent_id' => $agent1->id,
            'is_active' => true,
        ]);

        expect($member->hasAccessToAgent($agent2))->toBeFalse();
    });

    it('denies inactive member access to agent', function () {
        $user = User::factory()->create();
        $agent = Agent::factory()->create(['user_id' => $user->id]);

        $member = Member::factory()->create([
            'agent_id' => $agent->id,
            'is_active' => false,
        ]);

        // Check if member is active
        expect($member->is_active)->toBeFalse();
    });

    it('allows owner to revoke member access', function () {
        $user = User::factory()->create();
        $agent = Agent::factory()->create(['user_id' => $user->id]);

        $member = Member::factory()->create([
            'agent_id' => $agent->id,
            'is_active' => true,
        ]);

        $response = $this->actingAs($user)->delete(route('agents.team.member.delete', $agent), [
            'member_id' => $member->id,
        ]);

        $response->assertSuccessful();

        expect(Member::find($member->id))->toBeNull();
    });

    it('prevents non-owner from revoking member access', function () {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $agent = Agent::factory()->create(['user_id' => $owner->id]);

        $member = Member::factory()->create([
            'agent_id' => $agent->id,
            'is_active' => true,
        ]);

        $response = $this->actingAs($otherUser)->delete(route('agents.team.member.delete', $agent), [
            'member_id' => $member->id,
        ]);

        $response->assertStatus(403);

        expect(Member::find($member->id))->not->toBeNull();
    });
});

describe('Member Invitation', function () {
    it('sends invitation to new member', function () {
        $user = User::factory()->create();
        $agent = Agent::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)->post(route('agents.team.invite', $agent), [
            'email' => 'newmember@example.com',
            'name' => 'New Member',
        ]);

        $response->assertSuccessful();

        expect(Member::where('email', 'newmember@example.com')->exists())->toBeTrue();
    });

    it('allows invited member to create account', function () {
        $user = User::factory()->create();
        $agent = Agent::factory()->create(['user_id' => $user->id]);

        $member = Member::factory()->create([
            'agent_id' => $agent->id,
            'email' => 'member@example.com',
            'password' => null,
            'invitation_token' => 'valid-token',
        ]);

        $response = $this->get(route('agents.members.invitation.create', [
            'agent' => $agent,
            'token' => 'valid-token',
        ]));

        $response->assertOk();
    });

    it('rejects invitation with invalid token', function () {
        $user = User::factory()->create();
        $agent = Agent::factory()->create(['user_id' => $user->id]);

        $response = $this->get(route('agents.members.invitation.create', [
            'agent' => $agent,
            'token' => 'invalid-token',
        ]));

        $response->assertStatus(404);
    });
});
