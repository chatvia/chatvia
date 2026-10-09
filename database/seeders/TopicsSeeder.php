<?php

namespace Database\Seeders;

use App\Identity\Workspace\Models\User;
use Database\Seeders\Demo\SkipsInProduction;
use Domain\Agent\Models\Agent;
use Domain\Conversation\Models\Conversation;
use Domain\Conversation\Topic\Models\Topic;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Demo data: gives the demo user a set of default topics and assigns them to
 * the seeded conversations, so the topic analytics have something to show.
 * Skipped in production.
 */
class TopicsSeeder extends Seeder
{
    use SkipsInProduction;

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        if ($this->skipInProduction()) {
            return;
        }

        // Get the demo user
        $user = User::where('email', config('chatvia.seed.admin_email'))->first();

        if (! $user) {
            $this->command->warn('Demo user not found. Please run the main seeder first.');

            return;
        }

        // Ensure we have default topics for this user
        $this->createDefaultTopics($user);

        // Get agents for this user
        $agents = Agent::where('user_id', $user->id)->get();

        if ($agents->isEmpty()) {
            $this->command->warn('No agents found for demo user.');

            return;
        }

        // Get topics for this user
        $topics = Topic::where('user_id', $user->id)->get();

        if ($topics->isEmpty()) {
            $this->command->warn('No topics found. Please check the topics table.');

            return;
        }

        $this->command->info("Found {$agents->count()} agents and {$topics->count()} topics");

        // Associate all topics with each agent
        foreach ($agents as $agent) {
            $topicIds = $topics->pluck('id')->toArray();
            $agent->topics()->syncWithoutDetaching($topicIds);
            $this->command->info("Assigned {$topics->count()} topics to agent '{$agent->name}'");
        }

        // Verify agent_topics were created
        $agentTopicsCount = DB::table('agent_topic')->count();
        $this->command->info("Created {$agentTopicsCount} agent_topic records");

        // Associate topics with conversations based on their titles
        $this->assignTopicsToConversations($user);

        $this->command->info('Topics seeded successfully!');
    }

    /**
     * Create default topics for the user.
     */
    private function createDefaultTopics(User $user): void
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
            Topic::firstOrCreate(
                [
                    'user_id' => $user->id,
                    'topic' => $topic['topic'],
                ],
                ['description' => $topic['description']]
            );
        }

        $this->command->info('Created/verified '.count($topics).' default topics.');
    }

    /**
     * Assign topics to conversations based on their titles.
     */
    private function assignTopicsToConversations(User $user): void
    {
        // Get topics for mapping
        $topics = Topic::where('user_id', $user->id)->pluck('id', 'topic')->toArray();

        // Map conversation titles to topics
        $titleToTopic = [
            'Product Features Inquiry' => 'General Inquiry',
            'API Integration Help' => 'Integration Help',
            'Enterprise Pricing Inquiry' => 'Pricing & Billing',
            'Widget Not Loading Issue' => 'Bug Report',
            'Multi-language Support Request' => 'Feature Request',
            'Getting Started with ChatVia' => 'Getting Started',
            'Data Security Concerns' => 'Security & Privacy',
            'Slack Integration Setup' => 'Integration Help',
            'Upgrade Plan Question' => 'Pricing & Billing',
            'Finding API Documentation' => 'Documentation',
            'Improving Bot Accuracy' => 'Training & Best Practices',
            'Password Reset' => 'Account Management',
            'Cancellation Request' => 'Account Management',
        ];

        $conversationsUpdated = 0;

        // Get all conversations for this user's agents
        $agentIds = Agent::where('user_id', $user->id)->pluck('id');
        $conversations = Conversation::whereHas('session', function ($query) use ($agentIds): void {
            $query->whereIn('agent_id', $agentIds);
        })->whereDoesntHave('topics')->get();

        foreach ($conversations as $conversation) {
            $topicName = $titleToTopic[$conversation->title] ?? null;

            if ($topicName && isset($topics[$topicName])) {
                $conversation->topics()->syncWithoutDetaching([$topics[$topicName]]);
                $conversationsUpdated++;
            }
        }

        $this->command->info("Updated {$conversationsUpdated} conversations with topics");
    }
}
