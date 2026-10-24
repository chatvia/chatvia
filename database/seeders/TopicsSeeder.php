<?php

namespace Database\Seeders;

use App\Identity\Workspace\Models\Workspace;
use Database\Seeders\Demo\DemoAgents;
use Database\Seeders\Demo\SkipsInProduction;
use Domain\Conversation\Topic\Models\Topic;
use Illuminate\Database\Seeder;

/**
 * Demo data: adds Halden Home's own topics next to the system topics and
 * links every topic the workspace can use to each demo agent, as creating
 * an agent in the dashboard does. The analytics history and the chats tag
 * their conversations with these topics, so it runs before them. Skipped in
 * production.
 */
class TopicsSeeder extends Seeder
{
    use SkipsInProduction;

    public function run(): void
    {
        if ($this->skipInProduction()) {
            return;
        }

        $workspace = DemoAgents::workspace();

        if (! $workspace instanceof Workspace) {
            $this->command->warn('Demo workspace not found. Run DatabaseSeeder first.');

            return;
        }

        foreach (DemoAgents::WORKSPACE_TOPICS as $topic => $description) {
            Topic::query()->firstOrCreate(
                ['workspace_id' => $workspace->id, 'topic' => $topic],
                ['description' => $description],
            );
        }

        $topicIds = Topic::forWorkspace($workspace)->pluck('id');

        foreach (DemoAgents::in($workspace) as $agent) {
            $agent->topics()->syncWithoutDetaching($topicIds);
        }

        $this->command->info("Linked {$topicIds->count()} topics to the demo agents.");
    }
}
