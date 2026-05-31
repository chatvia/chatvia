<?php

namespace Database\Factories;

use Domain\Agents\Enums\LlmProviderEnum;
use Domain\Agents\Models\Llm;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Llm>
 */
class LlmFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
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

        $modelKey = $this->faker->randomElement(array_keys($models));
        $modelInfo = $models[$modelKey];

        return [
            'internal_name' => $modelKey,
            'external_name' => $modelInfo[0],
            'descriptive_name' => $modelInfo[1],
            'description' => $this->faker->paragraph(3),
            'icon' => $modelInfo[2],
            'context_window' => $this->faker->randomElement([4096, 8192, 16384, 32768, 65536, 128000, 200000]),
            'features' => [
                'multimodal' => $this->faker->boolean(80),
                'function_calling' => $this->faker->boolean(90),
                'streaming' => $this->faker->boolean(95),
                'vision' => $this->faker->boolean(70),
            ],
            'location' => $this->faker->randomElement(['us', 'eu', 'cn']),
            'is_reasoning' => $this->faker->boolean(60),
            'provider' => $this->faker->randomElement(LlmProviderEnum::cases()),
        ];
    }

    /**
     * Indicate that the model supports advanced reasoning.
     */
    public function withReasoning(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_reasoning' => true,
        ]);
    }

    /**
     * Indicate that the model is located in the US.
     */
    public function usBased(): static
    {
        return $this->state(fn (array $attributes) => [
            'location' => 'us',
        ]);
    }

    /**
     * Indicate that the model is located in Europe.
     */
    public function euBased(): static
    {
        return $this->state(fn (array $attributes) => [
            'location' => 'eu',
        ]);
    }

    /**
     * Indicate that the model is located in China.
     */
    public function cnBased(): static
    {
        return $this->state(fn (array $attributes) => [
            'location' => 'cn',
        ]);
    }
}
