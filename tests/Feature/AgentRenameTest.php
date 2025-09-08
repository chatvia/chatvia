<?php

use App\Models\Agent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AgentRenameTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_rename_their_agent()
    {
        $user = User::factory()->create();
        $agent = Agent::factory()->create([
            'user_id' => $user->id,
            'name' => 'Original Name',
        ]);

        $response = $this->actingAs($user)
            ->patch(route('agents.rename', $agent), [
                'name' => 'New Agent Name',
            ]);

        $response->assertRedirect();

        $agent->refresh();
        $this->assertEquals('New Agent Name', $agent->name);
    }

    public function test_user_cannot_rename_another_users_agent()
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        $agent = Agent::factory()->create([
            'user_id' => $user1->id,
            'name' => 'Original Name',
        ]);

        $response = $this->actingAs($user2)
            ->patch(route('agents.rename', $agent), [
                'name' => 'New Agent Name',
            ]);

        $response->assertStatus(404);

        $agent->refresh();
        $this->assertEquals('Original Name', $agent->name);
    }

    public function test_agent_name_is_required()
    {
        $user = User::factory()->create();
        $agent = Agent::factory()->create([
            'user_id' => $user->id,
            'name' => 'Original Name',
        ]);

        $response = $this->actingAs($user)
            ->patch(route('agents.rename', $agent), [
                'name' => '',
            ]);

        $response->assertSessionHasErrors('name');

        $agent->refresh();
        $this->assertEquals('Original Name', $agent->name);
    }
}
