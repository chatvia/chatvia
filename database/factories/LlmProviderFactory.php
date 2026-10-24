<?php

namespace Database\Factories;

use Domain\Llm\Enums\LlmProviderKind;
use Domain\Llm\Models\LlmProvider;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

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
            'kind' => LlmProviderKind::Custom,
            'name' => 'Test server',
            'base_url' => 'https://llm.test/v1',
            'api_key' => 'test-key',
            'headers' => null,
            'is_enabled' => true,
        ];
    }

    /**
     * A provider of the given kind: a custom server at a test address with a
     * test key, any other kind with neither (it calls with its account's key,
     * from config).
     */
    public function kind(LlmProviderKind $kind): static
    {
        return $this->state(fn (): array => [
            'kind' => $kind,
            'name' => Str::ucfirst($kind->value),
            'base_url' => $kind === LlmProviderKind::Custom ? 'https://llm.test/v1' : null,
            'api_key' => $kind === LlmProviderKind::Custom ? 'test-key' : null,
        ]);
    }

    public function disabled(): static
    {
        return $this->state(fn (): array => ['is_enabled' => false]);
    }
}
