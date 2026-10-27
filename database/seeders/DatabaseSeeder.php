<?php

namespace Database\Seeders;

use App\Identity\Workspace\Actions\Account\CreateWorkspaceAction;
use App\Identity\Workspace\Models\User;
use App\Identity\Workspace\Models\Workspace;
use Database\Seeders\Demo\DemoAgents;
use Database\Seeders\Demo\SkipsInProduction;
use Domain\Agent\Models\Agent;
use Domain\Llm\Models\Llm;
use Domain\Membership\Team\Actions\Team\CreateDefaultTeamAction;
use Domain\Membership\Team\Models\Team;
use Illuminate\Database\Seeder;
use Laravel\Pulse\Facades\Pulse;

/**
 * `php artisan db:seed`, for a local or staging database only: a starter LLM
 * catalog, an admin account whose workspace is the demo store Halden Home,
 * its three agents ({@see DemoAgents}), topics, 30 days of analytics and
 * two weeks of chats. Running it again resets the demo data to the same
 * shape. Nothing runs in production, where the admin setup in the panel adds
 * the AI provider and model. The admin login comes from SEED_ADMIN_EMAIL /
 * SEED_ADMIN_PASSWORD (chatvia.seed).
 */
class DatabaseSeeder extends Seeder
{
    use SkipsInProduction;

    public function run(): void
    {
        // The demo seeders write tens of thousands of rows. Pulse would keep
        // an entry for each of their queries until the end of the run.
        ini_set('memory_limit', '1G');
        Pulse::stopRecording();

        // Everything here is demo data with a well-known login.
        if ($this->skipInProduction()) {
            return;
        }

        // A starter catalog, so the demo agents have a model. The admin setup
        // still opens on the first visit to the admin panel.
        $this->call(LlmSeeder::class);

        $user = User::query()->firstOrCreate(
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
            $user->unsetRelation('workspaceMembership');
        }

        $workspace = $user->workspace();
        $workspace->update(['name' => DemoAgents::WORKSPACE_NAME]);
        $team = $user->primaryTeam() ?? app(CreateDefaultTeamAction::class)->execute($user);

        foreach (DemoAgents::AGENTS as $slug => $definition) {
            $this->seedAgent($workspace, $team, $slug, $definition);
        }

        // Topics first: the analytics history and the chats both tag their
        // conversations with them. The analytics history then lays down 30
        // days of rollups with no chats behind them (chats people have since
        // deleted), and ChatSeeder adds two weeks of chats with their own
        // analytics on top, so the analytics pages show both.
        $this->call(TopicsSeeder::class);
        $this->call(AnalyticsDemoSeeder::class);
        $this->call(ChatSeeder::class);

        $this->command->info('Database seeding completed successfully!');
    }

    /**
     * Create the agent, or bring an agent from an earlier seed run back to
     * its definition (found by its slug, which is unique).
     *
     * @param  array{name: string, external_id: string, system_instructions: string}  $definition
     */
    private function seedAgent(Workspace $workspace, Team $team, string $slug, array $definition): void
    {
        $attributes = [
            'name' => $definition['name'],
            'external_id' => $definition['external_id'],
            'system_instructions' => $definition['system_instructions'],
            'is_public' => true,
        ];

        $agent = Agent::withTrashed()
            ->where('workspace_id', $workspace->id)
            ->where(fn ($query) => $query->where('slug', $slug)->orWhere('external_id', $definition['external_id']))
            ->first();

        if (! $agent instanceof Agent) {
            $agent = Agent::factory()->create([
                ...$attributes,
                'workspace_id' => $workspace->id,
                'team_id' => $team->id,
                'slug' => $slug,
            ]);

            $this->command->info("Created the {$definition['name']} agent");
        }

        // The app gives a new agent the chat model of Settings → AI models,
        // which needs a provider key. The demo replies need a model either
        // way, so fall back to the first model of the catalog.
        $agent->forceFill([
            ...$attributes,
            'slug' => $slug,
            'deleted_at' => null,
            'llm_id' => $agent->llm_id ?? Llm::query()->where('is_enabled', true)->orderBy('id')->value('id'),
        ])->save();
    }
}
