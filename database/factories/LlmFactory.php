<?php

namespace Database\Factories;

use Domain\Llm\Models\Llm;
use Domain\Llm\Models\LlmProvider;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Builds a model row for the `llms` catalog, on a new test provider unless
 * one is given (`->for($provider, 'provider')`).
 *
 * @extends Factory<Llm>
 */
class LlmFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    #[\Override]
    protected $model = Llm::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        // A model name is unique per provider, so the default gets a suffix.
        // Tests that need a specific name set it.
        return [
            'name' => 'test-model-'.fake()->unique()->numerify('########'),
            'capabilities' => ['reasoning' => false],
            'is_enabled' => true,
            'llm_provider_id' => LlmProvider::factory(),
        ];
    }

    public function disabled(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_enabled' => false,
        ]);
    }

    /**
     * Indicate that the model supports advanced reasoning.
     */
    public function withReasoning(): static
    {
        return $this->state(fn (array $attributes): array => [
            'capabilities' => ['reasoning' => true],
        ]);
    }
}
