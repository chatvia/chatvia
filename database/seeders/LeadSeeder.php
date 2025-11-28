<?php

namespace Database\Seeders;

use App\Models\Agent;
use App\Models\ChatConversation;
use App\Models\Lead;
use Illuminate\Database\Seeder;

class LeadSeeder extends Seeder
{
    /**
     * Sample messages that leads might send.
     *
     * @var array<string>
     */
    private array $leadMessages = [
        'I\'m interested in learning more about your pricing plans for my business.',
        'We\'re looking for an AI solution for our customer support team.',
        'Can you send me more information about enterprise features?',
        'I\'d like to schedule a demo of your platform.',
        'Our company is evaluating several solutions and we have some questions.',
        'I saw your product mentioned in an article and wanted to learn more.',
        'We need help with implementing a knowledge base for our organization.',
        'Looking for a chatbot solution that can handle multiple languages.',
        'I have a team of 50+ people who could benefit from this.',
        'What kind of analytics and reporting do you offer?',
        'We\'re currently using a competitor but exploring alternatives.',
        'Can your platform integrate with our existing CRM system?',
        'I need to understand your data security practices better.',
        'We\'re a startup looking for affordable AI assistance.',
        'Our support volume has grown and we need automation.',
        null, // Some leads don't leave a message
    ];

    /**
     * Sample job titles.
     *
     * @var array<string>
     */
    private array $jobTitles = [
        'CEO',
        'CTO',
        'VP of Engineering',
        'Director of Operations',
        'Head of Customer Support',
        'Product Manager',
        'Engineering Manager',
        'IT Director',
        'Customer Success Manager',
        'Marketing Director',
        'Business Development Manager',
        'Chief Operating Officer',
        'Technical Lead',
        'Digital Transformation Lead',
        'Innovation Manager',
        null, // Some leads don't provide job title
    ];

    /**
     * Sample referrer URLs.
     *
     * @var array<string|null>
     */
    private array $referrerUrls = [
        'https://www.google.com/search?q=ai+chatbot+solutions',
        'https://www.google.com/search?q=knowledge+base+software',
        'https://twitter.com/techcompany/status/123456789',
        'https://www.linkedin.com/posts/ai-tools-review',
        'https://www.producthunt.com/posts/chatvia',
        'https://news.ycombinator.com/item?id=12345',
        'https://www.reddit.com/r/saas/comments/abc123',
        'https://techcrunch.com/2024/ai-tools-review',
        'https://www.capterra.com/ai-chatbot-software',
        'https://www.g2.com/products/chatvia/reviews',
        null, // Direct traffic
    ];

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Make sure we have agents that collect leads
        $agents = Agent::where('collect_leads', true)->get();

        if ($agents->isEmpty()) {
            // Fall back to all agents if none have lead collection enabled
            $agents = Agent::all();
        }

        if ($agents->isEmpty()) {
            $this->command->warn('No agents found. Please run the database seeder first.');

            return;
        }

        $this->command->info('Creating leads...');

        // Get conversations without leads, ensuring they have agents
        $conversationsWithoutLeads = ChatConversation::whereDoesntHave('lead')
            ->whereHas('session.agent')
            ->inRandomOrder()
            ->limit(150)
            ->get();

        if ($conversationsWithoutLeads->isEmpty()) {
            $this->command->warn('No conversations available for leads. Skipping lead creation.');

            return;
        }

        $leadsCreated = 0;

        // Create leads with conversation associations (leads MUST have a conversation)
        foreach ($conversationsWithoutLeads as $conversation) {
            $agent = $conversation->session->agent;

            if (! $agent) {
                continue;
            }

            // Create the lead
            $this->createLead($agent, $conversation);
            $leadsCreated++;
        }

        $this->command->info("Created {$leadsCreated} leads.");
    }

    /**
     * Create a single lead.
     */
    private function createLead(Agent $agent, ChatConversation $conversation): void
    {
        $locales = ['en', 'en_US', 'en_GB', 'es', 'es_MX', 'fr', 'fr_CA', 'de', 'pt_BR', 'it', 'nl', 'ja', 'zh_CN'];

        $companyTypes = [
            'Tech Startup',
            'SaaS Company',
            'Enterprise Corporation',
            'Marketing Agency',
            'E-commerce Business',
            'Healthcare Provider',
            'Financial Services',
            'Educational Institution',
            'Government Agency',
            'Non-profit Organization',
            'Manufacturing Company',
            'Consulting Firm',
            'Law Firm',
            'Real Estate Company',
        ];

        $cities = [
            'San Francisco', 'New York', 'Los Angeles', 'Chicago', 'Austin',
            'Seattle', 'Boston', 'Denver', 'Portland', 'Miami',
            'London', 'Paris', 'Berlin', 'Tokyo', 'Sydney',
            'Toronto', 'Vancouver', 'Dublin', 'Amsterdam', 'Singapore',
        ];

        try {
            Lead::create([
                'agent_id' => $agent->id,
                'chat_conversation_id' => $conversation->id,
                'name' => fake()->name(),
                'email' => fake()->unique()->companyEmail(),
                'phone' => fake()->optional(0.7)->phoneNumber(),
                'job_title' => fake()->optional(0.7)->randomElement($this->jobTitles),
                'company' => fake()->optional(0.8)->randomElement([
                    fake()->company(),
                    fake()->company().' '.fake()->randomElement($companyTypes),
                ]),
                'location' => fake()->optional(0.6)->randomElement($cities),
                'message' => fake()->optional(0.75)->randomElement($this->leadMessages),
                'referrer_url' => fake()->optional(0.5)->randomElement($this->referrerUrls),
                'page_url' => fake()->optional(0.7)->randomElement([
                    '/pricing',
                    '/features',
                    '/enterprise',
                    '/demo',
                    '/contact',
                    '/',
                    '/docs',
                    '/blog/ai-chatbots',
                ]),
                'locale' => fake()->randomElement($locales),
                'ip_address' => fake()->ipv4(),
                'user_agent' => fake()->userAgent(),
                'created_at' => fake()->dateTimeBetween('-90 days', 'now'),
            ]);
        } catch (\Illuminate\Database\UniqueConstraintViolationException $e) {
            // Skip duplicate email constraints
        }
    }
}
