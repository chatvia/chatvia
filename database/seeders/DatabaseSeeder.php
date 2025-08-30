<?php

namespace Database\Seeders;

use App\Models\Agent;
use App\Models\AgentFile;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::factory()->create([
            'name' => 'Ahmad',
            'email' => 'ahmad@mayahi.net',
            'is_admin' => 1,
        ]);

        $agent = Agent::factory()->create([
            'user_id' => $user->id,
            'name' => 'Translation',
            'sub_domain' => 'translation',
            'system_instructions' => config('prompts.knowledge-based-system-instruction'),
            'is_public' => 1,
            'is_active' => 1,
            'type' => 'knowledge_base',
            'external_id' => '407f7fdfa1cf18',
        ]);

        AgentFile::factory()->create(['agent_id' => $agent->id]);

        $user2 = User::factory()->create([
            'name' => 'Ahmad',
            'email' => 'ahmad.mayagi@gmail.com',
            'is_admin' => 0,
        ]);

        Agent::factory()->create([
            'user_id' => $user->id,
            'name' => 'Assistant',
            'sub_domain' => 'assistant',
            'system_instructions' => config('prompts.knowledge-based-system-instruction'),
            'is_public' => 1,
            'is_active' => 1,
            'type' => 'widget',
            'external_id' => '407f7fdfa1cf19'
        ]);
    }
}
