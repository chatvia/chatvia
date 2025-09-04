<?php

namespace Database\Factories;

use App\Models\Agent;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Lead>
 */
class LeadFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $status = $this->faker->randomElement(['new', 'contacted', 'qualified', 'converted', 'lost']);
        $pipelineStages = ['new', 'contacted', 'qualified', 'proposal-sent', 'negotiation', 'converted', 'lost'];
        $pipelineStage = $this->faker->randomElement($pipelineStages);

        return [
            'session_id' => $this->faker->unique()->regexify('[A-Za-z0-9]{60}'),
            'conversation_id' => $this->faker->uuid(),
            'agent_id' => Agent::inRandomOrder()->first()?->id ?? Agent::factory(),

            // Contact Information
            'name' => $this->faker->name(),
            'email' => $this->faker->safeEmail(),
            'phone' => $this->faker->optional(0.7)->phoneNumber(),
            'company' => $this->faker->optional(0.6)->company(),
            'location' => $this->faker->optional(0.5)->city(),
            'message' => $this->faker->optional(0.8)->paragraph(2),

            // Pipeline & Status
            'pipeline_stage' => $pipelineStage,
            'status' => $status,
            'priority' => $this->faker->randomElement(['low', 'medium', 'high', 'urgent']),
            'score' => $this->faker->numberBetween(0, 100),
            'value' => $this->faker->optional(0.6)->numberBetween(1000, 100000),

            // Tags & Labels
            'tags' => $this->faker->optional(0.7)->randomElements([
                'High Value', 'Real Estate', 'Demo Requested', 'Hot Lead', 'Enterprise',
                'SMB', 'Referral', 'Website', 'Cold Outreach', 'Event Lead',
            ], $this->faker->numberBetween(1, 3)),
            'custom_fields' => $this->faker->optional(0.3)->randomElement([
                ['source' => 'website', 'campaign' => 'summer-2024'],
                ['budget' => '$10,000', 'timeline' => '3 months'],
                ['interest' => 'product demo', 'company_size' => '50-100'],
            ]),

            // Assignment & Team
            'assigned_to' => $this->faker->optional(0.8)->randomElement(User::pluck('id')->toArray()),
            'source' => $this->faker->randomElement(['widget', 'form', 'website', 'referral', 'cold_outreach']),

            // Notes & Communication
            'notes' => $this->faker->optional(0.6)->paragraph(),
            'internal_notes' => $this->faker->optional(0.3)->sentence(),

            // Follow-up & Tasks
            'next_follow_up' => $this->faker->optional(0.4)->dateTimeBetween('now', '+30 days'),
            'follow_up_type' => $this->faker->optional(0.4)->randomElement(['call', 'email', 'meeting', 'demo']),
            'follow_up_notes' => $this->faker->optional(0.3)->sentence(),

            // Tracking & Analytics
            'ip_address' => $this->faker->ipv4(),
            'user_agent' => $this->faker->userAgent(),
            'referrer' => $this->faker->optional(0.5)->url(),
            'utm_source' => $this->faker->optional(0.4)->randomElement(['google', 'facebook', 'linkedin', 'twitter']),
            'utm_medium' => $this->faker->optional(0.4)->randomElement(['cpc', 'organic', 'social', 'email']),
            'utm_campaign' => $this->faker->optional(0.4)->words(2, true),
            'page_views' => $this->faker->numberBetween(1, 20),
            'session_duration' => $this->faker->optional(0.7)->numberBetween(30, 3600), // seconds

            // Status Timestamps
            'contacted_at' => $status !== 'new' ? $this->faker->dateTimeBetween('-30 days', 'now') : null,
            'qualified_at' => in_array($status, ['qualified', 'converted']) ? $this->faker->dateTimeBetween('-20 days', 'now') : null,
            'converted_at' => $status === 'converted' ? $this->faker->dateTimeBetween('-10 days', 'now') : null,
            'lost_at' => $status === 'lost' ? $this->faker->dateTimeBetween('-10 days', 'now') : null,
            'last_activity_at' => $this->faker->dateTimeBetween('-7 days', 'now'),
        ];
    }

    public function newLead(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'new',
            'contacted_at' => null,
            'qualified_at' => null,
            'converted_at' => null,
        ]);
    }

    public function contacted(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'contacted',
            'contacted_at' => $this->faker->dateTimeBetween('-30 days', 'now'),
            'qualified_at' => null,
            'converted_at' => null,
        ]);
    }

    public function qualified(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'qualified',
            'contacted_at' => $this->faker->dateTimeBetween('-30 days', '-20 days'),
            'qualified_at' => $this->faker->dateTimeBetween('-20 days', 'now'),
            'converted_at' => null,
        ]);
    }

    public function converted(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'converted',
            'contacted_at' => $this->faker->dateTimeBetween('-30 days', '-20 days'),
            'qualified_at' => $this->faker->dateTimeBetween('-20 days', '-10 days'),
            'converted_at' => $this->faker->dateTimeBetween('-10 days', 'now'),
        ]);
    }
}
