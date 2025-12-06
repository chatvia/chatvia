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
        // Create the main demo user
        $user = User::firstOrCreate(
            ['email' => 'ahmad@mayahi.net'],
            [
                'name' => 'Ahmad Mayahi',
                'type' => 'admin',
                'password' => bcrypt('password'),
                'email_verified_at' => now(),
            ]
        );

        $this->command->info("Demo user created: {$user->email}");

        // Get or create primary team
        $team = $user->primaryTeam();
        if (! $team) {
            $team = app(\App\Services\Teams\TeamService::class)->createDefaultTeam($user);
            $this->command->info("Created default team: {$team->name}");
        }

        // Create Knowledge Base agent if it doesn't exist
        if (! Agent::where('external_id', 'e8c75295bf6572')->exists()) {
            Agent::factory()->create([
                'user_id' => $user->id,
                'team_id' => $team->id,
                'name' => 'Knowledge Base',
                'sub_domain' => 'knowledge',
                'system_instructions' => PromptLoader::load('knowledge/system'),
                'is_public' => 1,
                'type' => 'knowledge_base',
                'external_id' => 'e8c75295bf6572',
                'collect_leads' => false,
            ]);
            $this->command->info('Created Knowledge Base agent');
        }

        // Create Widget agent if it doesn't exist
        if (! Agent::where('external_id', '407f7fdfa1cf18')->exists()) {
            Agent::factory()->create([
                'user_id' => $user->id,
                'team_id' => $team->id,
                'name' => 'Support Widget',
                'sub_domain' => 'support',
                'system_instructions' => PromptLoader::load('knowledge/system'),
                'is_public' => 1,
                'type' => 'widget',
                'external_id' => '407f7fdfa1cf18',
                'collect_leads' => true,
            ]);
            $this->command->info('Created Support Widget agent');
        }

        // Create a third agent for demo variety
        if (! Agent::where('external_id', 'demo_sales_agent')->exists()) {
            Agent::factory()->create([
                'user_id' => $user->id,
                'team_id' => $team->id,
                'name' => 'Sales Assistant',
                'sub_domain' => 'sales',
                'system_instructions' => 'You are a helpful sales assistant that helps customers understand our products and pricing.',
                'is_public' => 1,
                'type' => 'widget',
                'external_id' => 'demo_sales_agent',
                'collect_leads' => true,
            ]);
            $this->command->info('Created Sales Assistant agent');
        }

        // Seed LLMs first (needed for chat messages)
        $this->call(LlmSeeder::class);

        // Seed chat data (sessions, conversations, messages)
        $this->call(ChatSeeder::class);

        // Seed topics
        $this->call(TopicsSeeder::class);

        // Seed leads
        $this->call(LeadSeeder::class);

        $this->command->info('Database seeding completed successfully!');
    }
}
