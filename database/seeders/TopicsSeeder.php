<?php

namespace Database\Seeders;

use App\Models\Agent;
use App\Models\ChatConversation;
use App\Models\ChatTopic;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class TopicsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Ensure we have default topics
        $this->createDefaultTopics();

        // Get all agents
        $agents = Agent::all();

        if ($agents->isEmpty()) {
            $this->command->warn('No agents found. Please create agents first.');

            return;
        }

        // Get all topics
        $topics = ChatTopic::all();

        if ($topics->isEmpty()) {
            $this->command->warn('No topics found. Please check the topics table.');

            return;
        }

        $this->command->info("Found {$agents->count()} agents and {$topics->count()} topics");

        // Associate topics with each agent
        foreach ($agents as $agent) {
            // Randomly select 5-10 topics for each agent
            $topicCount = min(10, $topics->count());
            $randomCount = rand(5, $topicCount);
            $randomTopics = $topics->random($randomCount);

            $this->command->info("Assigning {$randomCount} topics to agent '{$agent->name}'");

            $topicIds = [];
            foreach ($randomTopics as $topic) {
                $topicIds[] = $topic->id;
            }

            // Use sync to avoid duplicates
            $agent->topics()->syncWithoutDetaching($topicIds);
        }

        // Verify agent_topics were created
        $agentTopicsCount = DB::table('agent_topics')->count();
        $this->command->info("Created {$agentTopicsCount} agent_topics records");

        // Associate topics with conversations for testing analytics
        $conversations = ChatConversation::with('session.agent')
            ->whereHas('session.agent')
            ->whereNull('chat_topic_id')
            ->get();

        if ($conversations->isEmpty()) {
            $this->command->warn('No conversations found without topics.');

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
                continue;
            }

            // Randomly decide if this conversation should have a topic (70% chance)
            if (rand(1, 100) <= 70) {
                $randomTopic = $agentTopics->random();
                $conversation->update(['chat_topic_id' => $randomTopic->id]);
                $conversationsUpdated++;
            }
        }

        $this->command->info("Updated {$conversationsUpdated} conversations with topics");
        $this->command->info('Topics seeded successfully!');
    }

    /**
     * Create default topics if they don't exist.
     */
    private function createDefaultTopics(): void
    {
        $topics = [
            [
                'topic' => 'General Inquiry',
                'description' => 'General questions about the product or service',
            ],
            [
                'topic' => 'Technical Support',
                'description' => 'Technical issues and troubleshooting',
            ],
            [
                'topic' => 'Pricing & Billing',
                'description' => 'Questions about pricing, invoices, and payments',
            ],
            [
                'topic' => 'Feature Request',
                'description' => 'Suggestions for new features or improvements',
            ],
            [
                'topic' => 'Account Management',
                'description' => 'Account settings, password reset, profile updates',
            ],
            [
                'topic' => 'Integration Help',
                'description' => 'Help with API and third-party integrations',
            ],
            [
                'topic' => 'Getting Started',
                'description' => 'Onboarding and initial setup questions',
            ],
            [
                'topic' => 'Bug Report',
                'description' => 'Reporting software bugs and issues',
            ],
            [
                'topic' => 'Sales Inquiry',
                'description' => 'Pre-sales questions and enterprise inquiries',
            ],
            [
                'topic' => 'Security & Privacy',
                'description' => 'Questions about data security and privacy',
            ],
            [
                'topic' => 'Documentation',
                'description' => 'Help finding or understanding documentation',
            ],
            [
                'topic' => 'Training & Best Practices',
                'description' => 'Learning resources and recommended approaches',
            ],
            [
                'topic' => 'Feedback',
                'description' => 'General feedback about the product',
            ],
            [
                'topic' => 'Partnership',
                'description' => 'Partnership and collaboration inquiries',
            ],
            [
                'topic' => 'Data Export/Import',
                'description' => 'Questions about moving data in or out',
            ],
        ];

        foreach ($topics as $topic) {
            ChatTopic::firstOrCreate(
                ['topic' => $topic['topic']],
                ['description' => $topic['description']]
            );
        }

        $this->command->info('Created/verified '.count($topics).' default topics.');
    }
}
