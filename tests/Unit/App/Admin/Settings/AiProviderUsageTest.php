<?php

use App\Admin\Settings\AiProviderUsage;
use Domain\Llm\Enums\AiCapability;
use Domain\Llm\Enums\AiProvider;

/**
 * AiProviderUsage: the provider a service runs on now, for the chips of
 * Settings → AI providers. "Automatic" (or nothing saved) is the first
 * connected provider that can do it; a picked one counts only when it can.
 */
beforeEach(function (): void {
    foreach (AiProvider::cases() as $provider) {
        config([$provider->apiKeyConfig() => null]);
    }
});

it('names the provider a voice call runs on now', function (string $voice, array $connected, ?AiProvider $provider): void {
    config(['chatvia.ai.voice' => $voice]);
    connectAiProviders(...$connected);

    expect(app(AiProviderUsage::class)->providerFor(AiCapability::Voice))->toBe($provider);
})->with([
    'automatic, OpenAI connected' => ['auto', [AiProvider::OpenAi], AiProvider::OpenAi],
    'automatic, nothing that can' => ['auto', [AiProvider::Mistral], null],
    'OpenAI picked, not connected' => ['openai', [AiProvider::Mistral], AiProvider::OpenAi],
    'a provider without voice picked' => ['mistral', [AiProvider::Mistral], null],
]);
