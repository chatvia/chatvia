<?php

namespace Database\Factories;

use Domain\Agent\Models\Agent;
use Domain\Conversation\Models\Conversation;
use Domain\Lead\Models\Lead;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Lead>
 */
class LeadFactory extends Factory
{
    protected $model = Lead::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'agent_id' => Agent::inRandomOrder()->first()?->id ?? Agent::factory(),
            'chat_conversation_id' => null,

            // Contact Information
            'name' => $this->faker->name(),
            'email' => $this->faker->safeEmail(),
            'phone' => $this->faker->optional(0.7)->phoneNumber(),
            'job_title' => $this->faker->optional(0.5)->jobTitle(),
            'company' => $this->faker->optional(0.6)->company(),
            'location' => $this->faker->optional(0.5)->city(),
            'message' => $this->faker->optional(0.8)->paragraph(2),

            // Tracking & Analytics
            'referrer_url' => $this->faker->optional(0.5)->url(),
            'page_url' => $this->faker->optional(0.6)->url(),
            'locale' => $this->faker->optional(0.7)->randomElement(['en', 'es', 'fr', 'de', 'pt', 'it', 'nl', 'ja', 'zh']),
            'ip_address' => $this->faker->ipv4(),
            'user_agent' => $this->faker->userAgent(),
        ];
    }

    public function forAgent(Agent $agent): static
    {
        return $this->state(fn (array $attributes) => [
            'agent_id' => $agent->id,
        ]);
    }

    public function withConversation(Conversation $conversation): static
    {
        return $this->state(fn (array $attributes) => [
            'chat_conversation_id' => $conversation->id,
        ]);
    }

    public function withCompanyInfo(): static
    {
        return $this->state(fn (array $attributes) => [
            'company' => $this->faker->company(),
            'job_title' => $this->faker->jobTitle(),
        ]);
    }

    public function withMessage(): static
    {
        return $this->state(fn (array $attributes) => [
            'message' => $this->faker->paragraph(3),
        ]);
    }
}
