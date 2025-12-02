<?php

namespace Database\Seeders;

use App\Models\Agent;
use App\Models\ChatConversation;
use App\Models\ChatMessage;
use App\Models\ChatSession;
use App\Models\Country;
use App\Models\Member;
use App\Models\User;
use Illuminate\Database\Seeder;

class ChatSeeder extends Seeder
{
    /**
     * Sample user questions for realistic conversations.
     *
     * @var array<string>
     */
    private array $userQuestions = [
        // General inquiries
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
        'I have a question about your product.',
        'Could you clarify that?',
        'That makes sense, thanks!',
        'What about enterprise features?',
        'How do I upgrade my account?',

        // Technical questions
        'How do I integrate your API?',
        'What programming languages do you support?',
        'Is there a rate limit on API calls?',
        'How do I authenticate with your service?',
        'Where can I find the documentation?',
        'I\'m getting an error, can you help?',
        'How do webhooks work?',
        'Can I export my data?',

        // Support questions
        'I forgot my password, what should I do?',
        'My account seems to be locked.',
        'I\'m not receiving emails from you.',
        'How do I contact support?',
        'Can I get a refund?',
        'How do I cancel my subscription?',

        // Feature requests
        'Do you have a mobile app?',
        'Can I use this offline?',
        'Do you support multiple languages?',
        'Is there dark mode?',
        'Can multiple team members use one account?',

        // Comparison questions
        'How are you different from competitors?',
        'Why should I choose your product?',
        'What makes your AI special?',
        'Do you have any case studies?',

        // Follow-ups
        'Thanks, that\'s helpful!',
        'One more question...',
        'Perfect, that\'s exactly what I needed.',
        'I\'ll try that and get back to you.',
        'Could you provide more details?',
    ];

    /**
     * Sample assistant responses for realistic conversations.
     *
     * @var array<string>
     */
    private array $assistantResponses = [
        // Greetings and general
        'Hello! I\'d be happy to help you. What would you like to know?',
        'Of course! I\'m here to assist you with any questions you may have.',
        'Welcome! Let me know how I can help you today.',

        // Features
        'Our platform offers a wide range of features including real-time collaboration, advanced analytics, and seamless integrations with popular tools.',
        'We provide AI-powered assistance, document management, knowledge base creation, and customizable workflows to streamline your operations.',
        'Key features include: automated responses, conversation analytics, multi-channel support, and team collaboration tools.',

        // Getting started
        'Getting started is easy! First, create an account, then follow our quick setup wizard to configure your workspace.',
        'You can begin by exploring our documentation at docs.example.com, or I can walk you through the basics right now.',
        'The first step is to create your workspace and invite team members. After that, you can start uploading documents and training your AI assistant.',

        // Pricing
        'We offer several pricing tiers to fit different needs: Starter, Professional, and Enterprise. Each comes with its own set of features and limits.',
        'Our pricing is based on usage and features. The Starter plan is perfect for individuals, while Professional and Enterprise plans offer more capacity for teams.',
        'You can find detailed pricing information on our website. We also offer a free trial so you can test all features before committing.',

        // Security
        'Security is our top priority. We use end-to-end encryption, regular security audits, and comply with SOC 2 Type II and GDPR standards.',
        'Your data is protected with 256-bit AES encryption both in transit and at rest. We also offer two-factor authentication for additional security.',
        'We maintain strict security protocols including regular penetration testing, access controls, and comprehensive audit logging.',

        // Integration
        'Yes! We integrate with over 50+ popular services including Slack, Google Workspace, Microsoft 365, and many more.',
        'Our API allows you to connect with virtually any service. We also have pre-built integrations for common tools.',
        'Integration is straightforward with our REST API and webhooks. Check out our developer documentation for detailed guides.',

        // Technical
        'Our API supports multiple programming languages including Python, JavaScript, Ruby, PHP, and Go. We provide official SDKs for most languages.',
        'The rate limit depends on your plan. Starter has 100 requests/minute, Professional has 1,000, and Enterprise has custom limits.',
        'Authentication is done via API keys or OAuth 2.0. You can generate API keys from your dashboard settings.',

        // Support
        'For password reset, click "Forgot Password" on the login page. You\'ll receive an email with instructions within a few minutes.',
        'If your account is locked, please contact our support team at support@example.com and they\'ll help you regain access.',
        'You can reach our support team via email, live chat, or phone. Enterprise customers also have access to dedicated support managers.',

        // Closing
        'You\'re welcome! Is there anything else I can help you with?',
        'I\'m glad that helped! Feel free to ask if you need anything else.',
        'Let me know if you have any other questions. I\'m here to help!',
        'Great! Don\'t hesitate to reach out if you need further assistance.',
    ];

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get existing agents
        $agents = Agent::all();

        if ($agents->isEmpty()) {
            $this->command->warn('No agents found. Please run the main database seeder first.');

            return;
        }

        // Ensure we have LLMs in the database
        $llmCount = \App\Models\Llm::count();
        if ($llmCount === 0) {
            $this->command->warn('No LLMs found. Please run the LlmSeeder first.');

            return;
        }

        // Get or create members for testing
        $this->command->info('Creating members...');
        $members = Member::all();

        if ($members->count() < 20) {
            // Create members for different users to avoid unique constraint
            $users = User::all();
            $neededMembers = 20 - $members->count();

            foreach ($users as $user) {
                if ($neededMembers <= 0) {
                    break;
                }

                // Check if this user already has non-owner members
                $existingMemberCount = Member::where('user_id', $user->id)
                    ->where('is_owner', false)
                    ->count();

                if ($existingMemberCount === 0) {
                    try {
                        Member::factory()->create([
                            'user_id' => $user->id,
                            'is_owner' => false,
                        ]);
                        $neededMembers--;
                    } catch (\Exception $e) {
                        // Skip if constraint violation
                        continue;
                    }
                }
            }

            $members = Member::all();
        }

        // Get countries for sessions
        $countries = Country::pluck('id')->toArray();

        $this->command->info('Creating chat sessions and conversations...');

        // Create sessions with conversations and messages for each agent
        foreach ($agents as $agent) {
            $sessionCount = rand(15, 30); // 15-30 sessions per agent

            for ($i = 0; $i < $sessionCount; $i++) {
                $this->createSessionWithConversations(
                    agent: $agent,
                    members: $members,
                    countries: $countries,
                    withMember: $i < 8 // First 8 sessions have members
                );
            }
        }

        $totalSessions = ChatSession::count();
        $totalConversations = ChatConversation::count();
        $totalMessages = ChatMessage::count();

        $this->command->info('Chat data seeded successfully!');
        $this->command->info("  - Sessions: {$totalSessions}");
        $this->command->info("  - Conversations: {$totalConversations}");
        $this->command->info("  - Messages: {$totalMessages}");
    }

    /**
     * Create a session with conversations and messages.
     *
     * @param  \Illuminate\Database\Eloquent\Collection<int, Member>  $members
     * @param  array<int>  $countries
     */
    private function createSessionWithConversations(
        Agent $agent,
        $members,
        array $countries,
        bool $withMember = false
    ): void {
        $sessionData = [
            'agent_id' => $agent->id,
            'ip_address' => fake()->ipv4(),
            'user_agent' => fake()->userAgent(),
            'country_id' => ! empty($countries) ? fake()->randomElement($countries) : null,
            'device_type' => fake()->randomElement(['desktop', 'mobile', 'tablet']),
            'browser' => fake()->randomElement(['Chrome', 'Firefox', 'Safari', 'Edge']),
            'os' => fake()->randomElement(['Windows', 'macOS', 'iOS', 'Android', 'Linux']),
            'city' => fake()->city(),
            'locale' => fake()->randomElement(['en', 'es', 'fr', 'de', 'pt']),
            'referrer_url' => fake()->optional(0.6)->url(),
            'page_url' => fake()->url(),
            'created_at' => fake()->dateTimeBetween('-60 days', 'now'),
        ];

        if ($withMember && $members->isNotEmpty()) {
            $sessionData['member_id'] = $members->random()->id;
        }

        $session = ChatSession::create($sessionData);

        // Create 1-4 conversations per session
        $conversationCount = rand(1, 4);

        for ($i = 0; $i < $conversationCount; $i++) {
            $this->createConversationWithMessages($session);
        }
    }

    /**
     * Create a conversation with messages.
     */
    private function createConversationWithMessages(ChatSession $session): void
    {
        $conversationTitles = [
            'General inquiry',
            'Product question',
            'Technical support',
            'Pricing question',
            'Feature request',
            'Getting started',
            'Integration help',
            'Account assistance',
            'API documentation',
            'Security question',
            null, // Some conversations don't have titles
        ];

        $conversation = ChatConversation::create([
            'chat_session_id' => $session->id,
            'title' => fake()->randomElement($conversationTitles),
            'created_at' => $session->created_at,
        ]);

        // Create a realistic conversation flow (2-15 message pairs)
        $messagePairs = rand(2, 15);
        $startTime = $conversation->created_at;

        // Get LLM IDs for assistant messages
        $llmIds = \App\Models\Llm::pluck('id')->toArray();

        for ($i = 0; $i < $messagePairs; $i++) {
            // Add random time offset for realistic conversation timing
            $minuteOffset = $i * rand(1, 5);

            // User message
            ChatMessage::create([
                'chat_conversation_id' => $conversation->id,
                'content' => fake()->randomElement($this->userQuestions),
                'role' => 'user',
                'created_at' => $startTime->copy()->addMinutes($minuteOffset),
            ]);

            // Assistant response
            $hasPositiveFeedback = rand(1, 100) <= 15; // 15% positive feedback
            $hasNegativeFeedback = ! $hasPositiveFeedback && rand(1, 100) <= 5; // 5% negative feedback

            ChatMessage::create([
                'chat_conversation_id' => $conversation->id,
                'content' => fake()->randomElement($this->assistantResponses),
                'role' => 'assistant',
                'llm_id' => ! empty($llmIds) ? fake()->randomElement($llmIds) : null,
                'confidence_score' => fake()->randomFloat(4, 0.65, 0.99),
                'response_time_ms' => fake()->numberBetween(300, 5000),
                'prompt_tokens' => fake()->numberBetween(100, 1000),
                'completion_tokens' => fake()->numberBetween(50, 500),
                'cost' => fake()->randomFloat(6, 0.0001, 0.05),
                'sentiment' => fake()->randomElement(['positive', 'neutral', 'negative']),
                'tone' => fake()->optional(0.3)->randomElement(['neutral', 'frustrated', 'urgent', 'confused', 'satisfied', 'formal', 'casual']),
                'is_direct_response' => fake()->boolean(80),
                'feedback' => $hasPositiveFeedback ? 'positive' : ($hasNegativeFeedback ? 'negative' : null),
                'created_at' => $startTime->copy()->addMinutes($minuteOffset + 1),
            ]);
        }
    }
}
