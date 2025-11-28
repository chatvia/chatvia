<?php

namespace Database\Seeders;

use App\Models\Agent;
use App\Models\User;
use App\Services\Prompts\PromptLoader;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Create or find main admin user
        $admin = User::firstOrCreate(
            ['email' => 'ahmad@mayahi.net'],
            [
                'name' => 'Ahmad',
                'is_admin' => 1,
                'password' => bcrypt('password'),
                'email_verified_at' => now(),
            ]
        );

        // Create additional users for testing if not enough exist
        $this->command->info('Creating test users...');
        $existingUsersCount = User::count();
        if ($existingUsersCount < 11) {
            User::factory(11 - $existingUsersCount)->create();
        }
        $users = User::where('id', '!=', $admin->id)->get();

        // Create agents for admin if they don't exist
        if (! Agent::where('external_id', 'e8c75295bf6572')->exists()) {
            Agent::factory()->create([
                'user_id' => $admin->id,
                'name' => 'Knowledge Base',
                'sub_domain' => 'knowledge',
                'system_instructions' => PromptLoader::load('knowledge/system'),
                'is_public' => 1,
                'type' => 'knowledge_base',
                'external_id' => 'e8c75295bf6572',
                'collect_leads' => false,
            ]);
        }

        if (! Agent::where('external_id', '407f7fdfa1cf18')->exists()) {
            Agent::factory()->create([
                'user_id' => $admin->id,
                'name' => 'Widget',
                'sub_domain' => '',
                'system_instructions' => PromptLoader::load('knowledge/system'),
                'is_public' => 1,
                'type' => 'widget',
                'external_id' => '407f7fdfa1cf18',
                'collect_leads' => true,
            ]);
        }

        // Create additional agents for other users if needed
        $this->command->info('Creating additional agents...');
        foreach ($users->take(5) as $user) {
            $userAgentsCount = Agent::where('user_id', $user->id)->count();
            if ($userAgentsCount < 2) {
                Agent::factory()->count(rand(1, 3))->create([
                    'user_id' => $user->id,
                ]);
            }
        }

        // Seed LLMs
        // $this->call(LlmSeeder::class);

        // Seed chat data (sessions, conversations, messages)
        $this->call(ChatSeeder::class);

        // Seed topics
        $this->call(TopicsSeeder::class);

        // Seed leads
        $this->call(LeadSeeder::class);

        $this->command->info('Database seeding completed successfully!');
    }
}
