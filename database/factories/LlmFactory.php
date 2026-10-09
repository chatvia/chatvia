<?php

namespace Database\Factories;

use Domain\Llm\Enums\LlmProviderEnum;
use Domain\Llm\Models\Llm;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Builds a model row for the `llms` catalog. Names get a random numeric suffix
 * because `internal_name` and `external_name` are unique and the seeded catalog
 * uses the same base names.
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
        $models = [
            'gpt-4' => ['GPT-4', 'GPT-4 Turbo', 'gpt.svg'],
            'gpt-3.5-turbo' => ['GPT-3.5 Turbo', 'GPT-3.5 Turbo', 'gpt.svg'],
            'claude-3-opus' => ['Claude 3 Opus', 'Claude 3 Opus', 'claude.svg'],
            'claude-3-sonnet' => ['Claude 3 Sonnet', 'Claude 3 Sonnet', 'claude.svg'],
            'claude-3-haiku' => ['Claude 3 Haiku', 'Claude 3 Haiku', 'claude.svg'],
            'gemini-pro' => ['Gemini Pro', 'Gemini Pro', 'gemini.svg'],
            'llama-3-8b' => ['Llama 3 8B', 'Llama 3 8B', 'llama.svg'],
            'llama-3-70b' => ['Llama 3 70B', 'Llama 3 70B', 'llama.svg'],
        ];

        $modelKey = fake()->randomElement(array_keys($models));
        $modelInfo = $models[$modelKey];

        // internal_name and external_name are UNIQUE columns, and the keys above
        // overlap the seeded llms — so suffix the defaults to avoid collisions
        // across creates and the seed. Tests needing a specific name override these.
        $suffix = fake()->unique()->numerify('########');

        return [
            'internal_name' => $modelKey.'-'.$suffix,
            'external_name' => $modelInfo[0].' '.$suffix,
            'descriptive_name' => $modelInfo[1],
            'description' => fake()->paragraph(3),
            'icon' => $modelInfo[2],
            'context_window' => fake()->randomElement([4096, 8192, 16384, 32768, 65536, 128000, 200000]),
            'features' => [
                'multimodal' => fake()->boolean(80),
                'function_calling' => fake()->boolean(90),
                'streaming' => fake()->boolean(95),
                'vision' => fake()->boolean(70),
            ],
            'location' => fake()->randomElement(['us', 'eu', 'cn']),
            'is_reasoning' => fake()->boolean(60),
            'provider' => fake()->randomElement(LlmProviderEnum::cases()),
        ];
    }

    /**
     * Indicate that the model supports advanced reasoning.
     */
    public function withReasoning(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_reasoning' => true,
        ]);
    }

    /**
     * Indicate that the model is located in the US.
     */
    public function usBased(): static
    {
        return $this->state(fn (array $attributes): array => [
            'location' => 'us',
        ]);
    }

    /**
     * Indicate that the model is located in Europe.
     */
    public function euBased(): static
    {
        return $this->state(fn (array $attributes): array => [
            'location' => 'eu',
        ]);
    }

    /**
     * Indicate that the model is located in China.
     */
    public function cnBased(): static
    {
        return $this->state(fn (array $attributes): array => [
            'location' => 'cn',
        ]);
    }
}
