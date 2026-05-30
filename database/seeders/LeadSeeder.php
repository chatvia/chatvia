<?php

namespace Database\Seeders;

use App\Auth\Models\User;
use Domain\Agents\Models\Agent;
use Domain\Agents\Models\Lead;
use Domain\Chat\Models\ChatConversation;
use Illuminate\Database\Seeder;
use Illuminate\Database\UniqueConstraintViolationException;

class LeadSeeder extends Seeder
{
    /**
     * Demo leads with realistic data.
     *
     * @var array<array{name: string, email: string, phone: string|null, job_title: string|null, company: string|null, location: string|null, message: string|null}>
     */
    private array $demoLeads = [
        [
            'name' => 'Jennifer Martinez',
            'email' => 'jennifer.martinez@techstartup.io',
            'phone' => '+1 (415) 555-0123',
            'job_title' => 'VP of Engineering',
            'company' => 'TechStartup Inc',
            'location' => 'San Francisco',
            'message' => 'Looking to implement AI chatbots for our customer support team. We handle about 5,000 tickets per month.',
        ],
        [
            'name' => 'Michael Thompson',
            'email' => 'm.thompson@enterprise-corp.com',
            'phone' => '+1 (212) 555-0456',
            'job_title' => 'Chief Technology Officer',
            'company' => 'Enterprise Corp',
            'location' => 'New York',
            'message' => 'Interested in enterprise pricing for 500+ employees. Need SSO integration with Azure AD.',
        ],
        [
            'name' => 'Sarah Chen',
            'email' => 'sarah.chen@innovate.co',
            'phone' => '+1 (650) 555-0789',
            'job_title' => 'Product Manager',
            'company' => 'Innovate Co',
            'location' => 'Palo Alto',
            'message' => 'We need a knowledge base solution that can handle technical documentation. Currently using Zendesk.',
        ],
        [
            'name' => 'David Williams',
            'email' => 'david.w@healthcare-plus.org',
            'phone' => '+1 (617) 555-0234',
            'job_title' => 'IT Director',
            'company' => 'Healthcare Plus',
            'location' => 'Boston',
            'message' => 'Need HIPAA-compliant solution for patient support chatbot. Looking at BAA requirements.',
        ],
        [
            'name' => 'Emily Rodriguez',
            'email' => 'emily.r@globalfinance.com',
            'phone' => '+1 (312) 555-0567',
            'job_title' => 'Director of Digital Transformation',
            'company' => 'Global Finance Ltd',
            'location' => 'Chicago',
            'message' => 'Evaluating AI solutions for internal helpdesk and external customer support. Budget approved for Q1.',
        ],
        [
            'name' => 'James Anderson',
            'email' => 'james.anderson@retailgiant.com',
            'phone' => '+1 (206) 555-0890',
            'job_title' => 'Head of Customer Experience',
            'company' => 'RetailGiant Inc',
            'location' => 'Seattle',
            'message' => 'We get 50,000+ customer inquiries monthly. Need to reduce response times significantly.',
        ],
        [
            'name' => 'Lisa Park',
            'email' => 'lisa.park@saasplatform.io',
            'phone' => '+1 (512) 555-0345',
            'job_title' => 'Customer Success Manager',
            'company' => 'SaaS Platform',
            'location' => 'Austin',
            'message' => 'Looking for a solution that integrates with Intercom and Salesforce. Demo request.',
        ],
        [
            'name' => 'Robert Johnson',
            'email' => 'rjohnson@manufacturing-co.com',
            'phone' => '+1 (313) 555-0678',
            'job_title' => 'Operations Manager',
            'company' => 'Manufacturing Co',
            'location' => 'Detroit',
            'message' => 'Need to create internal knowledge base for 1,200 factory workers. Multilingual support required.',
        ],
        [
            'name' => 'Amanda Foster',
            'email' => 'amanda.foster@edutech.edu',
            'phone' => '+1 (303) 555-0912',
            'job_title' => 'Dean of Student Services',
            'company' => 'EduTech University',
            'location' => 'Denver',
            'message' => 'Interested in student support chatbot for admissions and enrollment questions.',
        ],
        [
            'name' => 'Thomas Lee',
            'email' => 'thomas.lee@legalpartners.com',
            'phone' => '+1 (404) 555-0234',
            'job_title' => 'Managing Partner',
            'company' => 'Legal Partners LLP',
            'location' => 'Atlanta',
            'message' => 'Looking for confidential client communication solution. Data security is paramount.',
        ],
        [
            'name' => 'Maria Garcia',
            'email' => 'mgarcia@travel-agency.com',
            'phone' => '+1 (305) 555-0567',
            'job_title' => 'Marketing Director',
            'company' => 'Travel Agency Pro',
            'location' => 'Miami',
            'message' => 'Need 24/7 booking support bot. Must handle multiple languages (EN, ES, PT).',
        ],
        [
            'name' => 'Christopher Davis',
            'email' => 'chris.davis@insurance-group.com',
            'phone' => '+1 (214) 555-0890',
            'job_title' => 'VP of Claims',
            'company' => 'Insurance Group Inc',
            'location' => 'Dallas',
            'message' => 'Want to automate initial claims intake. Processing 2,000+ claims monthly.',
        ],
        [
            'name' => 'Rachel Kim',
            'email' => 'rachel.kim@mediacompany.tv',
            'phone' => '+1 (310) 555-0123',
            'job_title' => 'Head of Viewer Relations',
            'company' => 'Media Company TV',
            'location' => 'Los Angeles',
            'message' => 'Need support bot for streaming service subscribers. High volume during new releases.',
        ],
        [
            'name' => 'Daniel Brown',
            'email' => 'dbrown@nonprofit-foundation.org',
            'phone' => '+1 (202) 555-0456',
            'job_title' => 'Executive Director',
            'company' => 'Nonprofit Foundation',
            'location' => 'Washington DC',
            'message' => 'Looking for affordable solution for donor support and volunteer coordination.',
        ],
        [
            'name' => 'Sophie Turner',
            'email' => 'sophie.t@uk-consulting.co.uk',
            'phone' => '+44 20 7555 0789',
            'job_title' => 'Senior Consultant',
            'company' => 'UK Consulting Group',
            'location' => 'London',
            'message' => 'Evaluating options for our clients. Need white-label capabilities and EU data residency.',
        ],
        [
            'name' => 'Hans Mueller',
            'email' => 'h.mueller@german-tech.de',
            'phone' => '+49 30 555 01234',
            'job_title' => 'Leiter IT',
            'company' => 'German Tech GmbH',
            'location' => 'Berlin',
            'message' => 'Brauchen deutsche Sprachunterstützung. DSGVO-Konformität ist wichtig.',
        ],
        [
            'name' => 'Yuki Tanaka',
            'email' => 'y.tanaka@tokyo-corp.jp',
            'phone' => '+81 3 5555 6789',
            'job_title' => 'Technology Manager',
            'company' => 'Tokyo Corporation',
            'location' => 'Tokyo',
            'message' => 'Looking for solution with Japanese language support. Need API for custom integration.',
        ],
        [
            'name' => 'Pierre Dubois',
            'email' => 'pierre.dubois@paris-startup.fr',
            'phone' => '+33 1 55 55 12 34',
            'job_title' => 'Co-founder',
            'company' => 'Paris Startup',
            'location' => 'Paris',
            'message' => 'Recherche solution de chatbot IA pour notre service client. Budget limité mais croissance rapide.',
        ],
        [
            'name' => 'Alex Novak',
            'email' => 'alex.novak@sydney-tech.au',
            'phone' => '+61 2 5555 7890',
            'job_title' => 'Head of Support',
            'company' => 'Sydney Tech',
            'location' => 'Sydney',
            'message' => 'Time zone support is crucial - need 24/7 coverage across APAC region.',
        ],
        [
            'name' => 'Priya Sharma',
            'email' => 'priya.sharma@bangalore-it.in',
            'phone' => '+91 80 5555 1234',
            'job_title' => 'Customer Support Lead',
            'company' => 'Bangalore IT Services',
            'location' => 'Bangalore',
            'message' => 'Managing support for multiple clients. Need multi-tenant solution with separate knowledge bases.',
        ],
    ];

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get the demo user
        $user = User::where('email', 'ahmad@mayahi.net')->first();

        if (! $user) {
            $this->command->warn('Demo user not found. Please run the main seeder first.');

            return;
        }

        $agents = Agent::where('user_id', $user->id)->get();

        if ($agents->isEmpty()) {
            $this->command->warn('No agents found for demo user.');

            return;
        }

        $this->command->info('Creating demo leads...');

        // Get conversations without leads for this user's agents
        $agentIds = $agents->pluck('id');
        $conversationsWithoutLeads = ChatConversation::whereDoesntHave('lead')
            ->whereHas('session', function ($query) use ($agentIds) {
                $query->whereIn('agent_id', $agentIds);
            })
            ->inRandomOrder()
            ->limit(count($this->demoLeads))
            ->get();

        if ($conversationsWithoutLeads->isEmpty()) {
            $this->command->warn('No conversations available for leads. Skipping lead creation.');

            return;
        }

        $leadsCreated = 0;

        // Create leads with conversation associations
        foreach ($conversationsWithoutLeads as $index => $conversation) {
            if (! isset($this->demoLeads[$index])) {
                break;
            }

            $leadData = $this->demoLeads[$index];
            $agent = $conversation->session->agent;

            if (! $agent) {
                continue;
            }

            try {
                $this->createLead($agent, $conversation, $leadData);
                $leadsCreated++;
            } catch (UniqueConstraintViolationException $e) {
                // Skip duplicate email constraints
                continue;
            }
        }

        $this->command->info("Created {$leadsCreated} demo leads.");
    }

    /**
     * Create a single lead with specific data.
     *
     * @param  array<string, mixed>  $leadData
     */
    private function createLead(Agent $agent, ChatConversation $conversation, array $leadData): void
    {
        $referrerUrls = [
            'https://www.google.com/search?q=ai+chatbot+solutions',
            'https://www.google.com/search?q=customer+support+automation',
            'https://www.linkedin.com/posts/ai-tools-comparison',
            'https://www.producthunt.com/posts/chatvia',
            'https://techcrunch.com/ai-chatbot-review',
            'https://www.g2.com/products/chatvia/reviews',
            null, // Direct traffic
        ];

        $pageUrls = [
            '/pricing',
            '/features',
            '/enterprise',
            '/demo',
            '/contact',
            '/',
            '/docs',
        ];

        $locales = ['en', 'en_US', 'en_GB', 'es', 'fr', 'de', 'ja', 'pt_BR'];

        Lead::create([
            'agent_id' => $agent->id,
            'chat_conversation_id' => $conversation->id,
            'name' => $leadData['name'],
            'email' => $leadData['email'],
            'phone' => $leadData['phone'],
            'job_title' => $leadData['job_title'],
            'company' => $leadData['company'],
            'location' => $leadData['location'],
            'message' => $leadData['message'],
            'referrer_url' => fake()->randomElement($referrerUrls),
            'page_url' => fake()->randomElement($pageUrls),
            'locale' => fake()->randomElement($locales),
            'ip_address' => fake()->ipv4(),
            'user_agent' => fake()->userAgent(),
            'created_at' => $conversation->created_at,
        ]);
    }
}
