<?php

use App\Models\Agent;
use App\Models\Member;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

describe('Member Login', function () {
    beforeEach(function () {
        // Create a test agent with a unique subdomain that will be available for all tests
        $this->user = User::factory()->create();
        $this->agent = Agent::factory()->create([
            'user_id' => $this->user->id,
        ]);
        $this->testUrl = 'http://'.$this->agent->sub_domain.'.localhost';
    });

    it('allows member to access agent with valid credentials', function () {
        $member = Member::factory()->create([
            'user_id' => $this->user->id,
            'email' => 'member@example.com',
            'password' => Hash::make('password'),
            'email_verified_at' => now(),
        ]);

        $member->agents()->attach($this->agent->id);

        expect($member->fresh()->hasAccessToAgent($this->agent))->toBeTrue();

        // Member login uses the chat.login route with agent's subdomain
        $response = $this->post($this->testUrl.'/chat/login', [
            'email' => 'member@example.com',
            'password' => 'password',
        ]);

        $response->assertRedirect();

        $this->assertAuthenticatedAs($member, 'member');
    });

    it('rejects member login without agent association', function () {
        $member = Member::factory()->create([
            'user_id' => $this->user->id,
            'email' => 'member@example.com',
            'password' => Hash::make('password'),
        ]);

        // Don't attach member to agent

        $response = $this->post($this->testUrl.'/chat/login', [
            'email' => 'member@example.com',
            'password' => 'password',
        ]);

        $response->assertSessionHasErrors('email');
    });

    it('rejects member login with wrong password', function () {
        $member = Member::factory()->create([
            'user_id' => $this->user->id,
            'email' => 'member@example.com',
            'password' => Hash::make('correct-password'),
            'email_verified_at' => now(),
        ]);

        // Attach member to agent
        $member->agents()->attach($this->agent->id);

        $response = $this->post($this->testUrl.'/chat/login', [
            'email' => 'member@example.com',
            'password' => 'wrong-password',
        ]);

        $response->assertSessionHasErrors('email');
    });

    it('rejects member accessing wrong agent', function () {
        $agent2 = Agent::factory()->create([
            'user_id' => $this->user->id,
            'sub_domain' => 'test2',
        ]);

        $member = Member::factory()->create([
            'user_id' => $this->user->id,
            'email' => 'member@example.com',
            'password' => Hash::make('password'),
        ]);

        // Attach member only to agent1 (the main test agent)
        $member->agents()->attach($this->agent->id);

        // Verify member cannot access agent2
        expect($member->hasAccessToAgent($agent2))->toBeFalse();
    });
});

describe('Member Password Management', function () {
    it('allows member to set password on first login', function () {
        $user = User::factory()->create();
        $agent = Agent::factory()->create(['user_id' => $user->id]);

        $member = Member::factory()->create([
            'user_id' => $user->id,
            'email' => 'member@example.com',
            'password' => null, // No password set
            'verification_token' => 'valid-token',
        ]);

        $member->agents()->attach($agent->id);

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
            'user_id' => $user->id,
            'email' => 'member@example.com',
            'password' => null,
            'verification_token' => 'valid-token',
        ]);

        $member->agents()->attach($agent->id);

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
            'user_id' => $user->id,
            'email' => 'member@example.com',
            'password' => Hash::make('old-password'),
        ]);

        $member->agents()->attach($agent->id);

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
            'user_id' => $user->id,
            'email' => 'member@example.com',
            'password' => Hash::make('correct-password'),
            'email_verified_at' => now(),
        ]);

        $member->agents()->attach($agent->id);

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
            'user_id' => $user->id,
            'email' => 'member@example.com',
            'password' => Hash::make('old-password'),
        ]);

        $member->agents()->attach($agent->id);

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
            'user_id' => $user->id,
        ]);

        $member->agents()->attach($agent->id);

        expect($member->hasAccessToAgent($agent))->toBeTrue();
    });

    it('denies member access to non-assigned agent', function () {
        $user = User::factory()->create();
        $agent1 = Agent::factory()->create(['user_id' => $user->id]);
        $agent2 = Agent::factory()->create(['user_id' => $user->id]);

        $member = Member::factory()->create([
            'user_id' => $user->id,
        ]);

        $member->agents()->attach($agent1->id);

        expect($member->hasAccessToAgent($agent2))->toBeFalse();
    });

    it('allows owner to revoke member access', function () {
        $user = User::factory()->create();
        $agent = Agent::factory()->create(['user_id' => $user->id]);

        $member = Member::factory()->create([
            'user_id' => $user->id,
        ]);

        $member->agents()->attach($agent->id);

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
            'user_id' => $owner->id,
        ]);

        $member->agents()->attach($agent->id);

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
            'user_id' => $user->id,
            'email' => 'member@example.com',
            'password' => null,
            'verification_token' => 'valid-token',
        ]);

        $member->agents()->attach($agent->id);

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
