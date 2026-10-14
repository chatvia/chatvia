<?php

namespace Database\Seeders;

use App\Identity\Workspace\Actions\Account\CreateWorkspaceAction;
use App\Identity\Workspace\Models\User;
use Database\Seeders\Demo\SkipsInProduction;
use Domain\Agent\Models\Agent;
use Domain\Membership\Team\Services\TeamService;
use Illuminate\Database\Seeder;

/**
 * `php artisan db:seed`, for a local or staging database only: a starter LLM
 * catalog, an admin account, three agents, fake chats and analytics, with
 * the admin setup marked as done. Nothing runs in production, where the
 * admin setup in the panel adds the AI provider and model. The admin login
 * comes from SEED_ADMIN_EMAIL / SEED_ADMIN_PASSWORD (chatvia.seed).
 */
class DatabaseSeeder extends Seeder
{
    use SkipsInProduction;

    public function run(): void
    {
        // The demo seeders write tens of thousands of rows.
        ini_set('memory_limit', '1G');

        // Everything here is demo data with a well-known login.
        if ($this->skipInProduction()) {
            return;
        }

        // A starter catalog, so the demo agents have a model. The admin setup
        // still opens on the first visit to the admin panel.
        $this->call(LlmSeeder::class);

        // Create the main demo user
        $user = User::firstOrCreate(
            ['email' => (string) config('chatvia.seed.admin_email')],
            [
                'name' => 'Demo Admin',
                'is_admin' => true,
                'password' => bcrypt((string) config('chatvia.seed.admin_password')),
                'email_verified_at' => now(),
            ]
        );

        $this->command->info("Demo user: {$user->email}");

        // The demo admin owns a workspace like any account; creating it also
        // creates the default team.
        if (! $user->workspaceMembership()->exists()) {
            app(CreateWorkspaceAction::class)->execute($user);
            $this->command->info('Created the demo workspace');
        }

        $workspace = $user->workspace();
        $team = $user->primaryTeam() ?? app(TeamService::class)->createDefaultTeam($user);

        if (! Agent::where('external_id', 'e8c75295bf6572')->exists()) {
            Agent::factory()->create([
                'workspace_id' => $workspace->id,
                'team_id' => $team->id,
                'name' => 'Knowledge Base',
                'slug' => 'knowledge',
                'system_instructions' => prompt('knowledge/system'),
                'is_public' => 1,
                'external_id' => 'e8c75295bf6572',
            ]);
            $this->command->info('Created Knowledge Base agent');
        }

        if (! Agent::where('external_id', '407f7fdfa1cf18')->exists()) {
            Agent::factory()->create([
                'workspace_id' => $workspace->id,
                'team_id' => $team->id,
                'name' => 'Customer Support',
                'slug' => 'support',
                'system_instructions' => prompt('knowledge/system'),
                'is_public' => 1,
                'external_id' => '407f7fdfa1cf18',
            ]);
            $this->command->info('Created Customer Support agent');
        }

        if (! Agent::where('external_id', 'demo_sales_agent')->exists()) {
            Agent::factory()->create([
                'workspace_id' => $workspace->id,
                'team_id' => $team->id,
                'name' => 'Sales Assistant',
                'slug' => 'sales',
                'system_instructions' => 'You are a helpful sales assistant that helps customers understand our products and pricing.',
                'is_public' => 1,
                'external_id' => 'demo_sales_agent',
            ]);
            $this->command->info('Created Sales Assistant agent');
        }

        // Analytics first: lays down 30 days of "historical" rollup data with
        // no chat_* backing — represents conversations the user has since
        // deleted. ChatSeeder then layers ~25-45 recent chats on top and fires
        // matching analytics events, so the analytics page shows the union.
        $this->call(AnalyticsDemoSeeder::class);

        // Seed chat data (sessions, conversations, messages) — also fires
        // analytics events for every row it creates.
        $this->call(ChatSeeder::class);

        // Seed topics
        $this->call(TopicsSeeder::class);

        $this->command->info('Database seeding completed successfully!');
    }
}
