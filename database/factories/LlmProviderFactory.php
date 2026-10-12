<?php

namespace Database\Factories;

use Domain\Llm\Enums\LlmProviderEnum;
use Domain\Llm\Models\LlmProvider;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Builds a connection to an LLM provider (`llm_providers`), by default an
 * OpenAI-compatible server with a fake key.
 *
 * @extends Factory<LlmProvider>
 */
class LlmProviderFactory extends Factory
{
    #[\Override]
    protected $model = LlmProvider::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'kind' => LlmProviderEnum::CUSTOM,
            'name' => 'Test server',
            'base_url' => 'https://llm.test/v1',
            'api_key' => 'test-key',
            'headers' => null,
            'is_enabled' => true,
        ];
    }

    /** A provider of the given kind, at its default address (a test address for a custom server). */
    public function kind(LlmProviderEnum $kind): static
    {
        return $this->state(fn (): array => [
            'kind' => $kind,
            'name' => ucfirst($kind->value),
            'base_url' => $kind === LlmProviderEnum::CUSTOM ? 'https://llm.test/v1' : null,
        ]);
    }

    public function disabled(): static
    {
        return $this->state(fn (): array => ['is_enabled' => false]);
    }
}
