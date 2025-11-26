<?php

namespace Database\Seeders;

use App\Models\Agent;
use App\Models\ChatConversation;
use App\Models\ChatTopic;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class TopicsSeeder extends Seeder
{
    public function run(): void
    {
        // Get all agents
        $agents = Agent::all();

        if ($agents->isEmpty()) {
            $this->command->warn('No agents found. Please create agents first.');

            return;
        }

        // Get all topics
        $topics = ChatTopic::all();

        if ($topics->isEmpty()) {
            $this->command->warn('No topics found. Please run migrations first.');

            return;
        }

        $this->command->info("Found {$agents->count()} agents and {$topics->count()} topics");

        // Associate topics with each agent
        foreach ($agents as $agent) {
            // Randomly select 5-8 topics for each agent
            $topicCount = min(8, $topics->count());
            $randomCount = rand(5, $topicCount);
            $randomTopics = $topics->random($randomCount);

            $this->command->info("Assigning {$randomCount} topics to agent '{$agent->name}'");

            $topicIds = [];
            foreach ($randomTopics as $topic) {
                $topicIds[] = $topic->id;
                $this->command->info("  - {$topic->topic}");
            }

            // Use sync to avoid duplicates
            $agent->topics()->syncWithoutDetaching($topicIds);
        }

        // Verify agent_topics were created
        $agentTopicsCount = DB::table('agent_topics')->count();
        $this->command->info("Created {$agentTopicsCount} agent_topics records");

        // Associate topics with conversations for testing analytics
        // (using chat_topic_id foreign key on chat_conversations)
        $conversations = ChatConversation::with('session.agent')
            ->whereHas('session.agent')
            ->whereNull('chat_topic_id')
            ->limit(50)
            ->get();

        if ($conversations->isEmpty()) {
            $this->command->warn('No conversations found without topics. Skipping conversation topic assignment.');

            return;
        }

        $this->command->info("Found {$conversations->count()} conversations without topics");

        $conversationsUpdated = 0;
        foreach ($conversations as $conversation) {
            $agent = $conversation->session->agent;

            if (! $agent) {
                continue;
            }

            // Get topics associated with this agent
            $agentTopics = $agent->topics()->get();

            if ($agentTopics->isEmpty()) {
                $this->command->warn("Agent '{$agent->name}' has no topics");

                continue;
            }

            // Assign ONE random topic to this conversation
            $randomTopic = $agentTopics->random();

            $conversation->update(['chat_topic_id' => $randomTopic->id]);
            $conversationsUpdated++;
        }

        $this->command->info("Updated {$conversationsUpdated} conversations with topics");
        $this->command->info('Topics seeded successfully!');
    }
}
