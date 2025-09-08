<?php

namespace Database\Seeders;

use App\Models\Agent;
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

        Agent::factory()->create([
            'user_id' => $user->id,
            'name' => 'Knowledge Base',
            'sub_domain' => 'knowledge',
            'system_instructions' => config('prompts.knowledge-based-system-instruction'),
            'is_public' => 1,
            'type' => 'knowledge_base',
            'external_id' => 'e8c75295bf6572',
            'collect_leads' => false,
        ]);

        Agent::factory()->create([
            'user_id' => $user->id,
            'name' => 'Widget',
            'sub_domain' => '',
            'system_instructions' => config('prompts.knowledge-based-system-instruction'),
            'is_public' => 1,
            'type' => 'widget',
            'external_id' => '407f7fdfa1cf18',
            'collect_leads' => true,
        ]);

    }
}
