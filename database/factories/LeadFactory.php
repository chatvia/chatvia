<?php

namespace Database\Factories;

use App\Models\Agent;
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
        $status = $this->faker->randomElement(['new', 'contacted', 'qualified', 'converted']);

        return [
            'session_id' => $this->faker->uuid(),
            'agent_id' => Agent::factory(),
            'name' => $this->faker->name(),
            'email' => $this->faker->safeEmail(),
            'phone' => $this->faker->optional(0.7)->phoneNumber(),
            'company' => $this->faker->optional(0.6)->company(),
            'message' => $this->faker->optional(0.8)->paragraph(2),
            'custom_fields' => $this->faker->optional(0.3)->randomElement([
                ['source' => 'website'],
                ['budget' => '$10,000', 'timeline' => '3 months'],
                ['interest' => 'product demo'],
            ]),
            'status' => $status,
            'ip_address' => $this->faker->ipv4(),
            'user_agent' => $this->faker->userAgent(),
            'referrer' => $this->faker->optional(0.5)->url(),
            'contacted_at' => $status !== 'new' ? $this->faker->dateTimeBetween('-30 days', 'now') : null,
            'qualified_at' => in_array($status, ['qualified', 'converted']) ? $this->faker->dateTimeBetween('-20 days', 'now') : null,
            'converted_at' => $status === 'converted' ? $this->faker->dateTimeBetween('-10 days', 'now') : null,
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
