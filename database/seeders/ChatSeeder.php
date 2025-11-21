<?php

namespace Database\Seeders;

use App\Models\Agent;
use App\Models\ChatConversation;
use App\Models\ChatMessage;
use App\Models\ChatSession;
use App\Models\Member;
use Illuminate\Database\Seeder;

class ChatSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get existing agents or create one if none exist
        $agents = Agent::all();

        if ($agents->isEmpty()) {
            $this->command->warn('No agents found. Please run DatabaseSeeder first to create agents.');

            return;
        }

        // Ensure we have LLMs in the database
        $llmCount = \App\Models\Llm::count();
        if ($llmCount === 0) {
            $this->command->warn('No LLMs found. Creating some LLMs first...');
            \App\Models\Llm::factory()->count(5)->create();
        }

        $this->command->info('Creating chat sessions...');

        // Create sessions with conversations and messages
        foreach ($agents->take(2) as $agent) {
            // Create 5 sessions per agent
            for ($i = 0; $i < 5; $i++) {
                $this->createSessionWithConversations($agent, $i < 2);
            }
        }

        $this->command->info('Chat data seeded successfully!');
    }

    /**
     * Create a session with conversations and messages.
     */
    private function createSessionWithConversations(Agent $agent, bool $withMember = false): void
    {
        // Create session (with or without member)
        $session = ChatSession::factory()
            ->for($agent)
            ->when($withMember, fn ($factory) => $factory->forMember())
            ->create();

        // Create 1-3 conversations per session
        $conversationCount = rand(1, 3);

        for ($i = 0; $i < $conversationCount; $i++) {
            $this->createConversationWithMessages($session);
        }
    }

    /**
     * Create a conversation with messages.
     */
    private function createConversationWithMessages(ChatSession $session): void
    {
        $conversation = ChatConversation::factory()
            ->for($session, 'session')
            ->create();

        // Create a realistic conversation flow (3-10 message pairs)
        $messagePairs = rand(3, 10);

        for ($i = 0; $i < $messagePairs; $i++) {
            // User message
            ChatMessage::factory()
                ->for($conversation, 'conversation')
                ->fromUser()
                ->create([
                    'content' => $this->getUserMessage($i),
                    'created_at' => now()->subMinutes($messagePairs * 2 - $i * 2),
                ]);

            // Assistant response
            ChatMessage::factory()
                ->for($conversation, 'conversation')
                ->fromAssistant()
                ->when(
                    rand(1, 10) > 8,
                    fn ($factory) => $factory->withPositiveFeedback()
                )
                ->when(
                    rand(1, 10) > 9,
                    fn ($factory) => $factory->withNegativeFeedback()
                )
                ->create([
                    'content' => $this->getAssistantMessage($i),
                    'created_at' => now()->subMinutes($messagePairs * 2 - $i * 2 - 1),
                ]);
        }
    }

    /**
     * Get a realistic user message based on message index.
     */
    private function getUserMessage(int $index): string
    {
        $messages = [
            'Hello! Can you help me with something?',
            'What are your features?',
            'How do I get started?',
            'Can you explain this in more detail?',
            'What are the pricing options?',
            'Do you support integrations?',
            'How secure is your platform?',
            'Can I customize the settings?',
            'What\'s the difference between the plans?',
            'Thank you for your help!',
            'I have a question about...',
            'Could you clarify that?',
            'That makes sense, thanks!',
            'What about enterprise features?',
            'How do I upgrade my account?',
        ];

        return $messages[array_rand($messages)];
    }

    /**
     * Get a realistic assistant message based on message index.
     */
    private function getAssistantMessage(int $index): string
    {
        $messages = [
            'Hello! I\'d be happy to help you. What would you like to know?',
            'Our platform offers a wide range of features including real-time collaboration, advanced analytics, and seamless integrations with popular tools.',
            'Getting started is easy! First, create an account, then follow our quick setup wizard to configure your workspace.',
            'Of course! Let me break that down for you in more detail...',
            'We offer several pricing tiers to fit different needs: Starter, Professional, and Enterprise. Each comes with its own set of features and limits.',
            'Yes! We integrate with over 50+ popular services including Slack, Google Workspace, Microsoft 365, and many more.',
            'Security is our top priority. We use end-to-end encryption, regular security audits, and comply with SOC 2 Type II and GDPR standards.',
            'Absolutely! You have full control over customization options including themes, workflows, and user permissions.',
            'The main differences are in the feature set and usage limits. Professional adds advanced analytics and API access, while Enterprise includes dedicated support and custom integrations.',
            'You\'re welcome! Is there anything else I can help you with?',
            'Sure, I\'m here to answer your questions.',
            'Let me clarify that for you...',
            'I\'m glad that helped! Feel free to ask if you need anything else.',
            'Enterprise features include dedicated account management, custom SLAs, advanced security controls, and priority support.',
            'To upgrade, simply go to your account settings and select the plan you\'d like to upgrade to. The change will be reflected immediately.',
        ];

        return $messages[array_rand($messages)];
    }
}
