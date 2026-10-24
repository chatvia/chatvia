<?php

declare(strict_types=1);

use Domain\Llm\Contracts\LlmProviderInterface;
use Domain\Llm\Enums\LlmProviderKind;
use Domain\Llm\Exceptions\LlmProviderUnavailableException;
use Domain\Llm\Models\LlmProvider;
use Domain\Llm\Services\LlmProviders;

/**
 * A chat client is only handed out for a provider that may be called: one
 * that is turned on and has the key of its account.
 */
beforeEach(function (): void {
    config(['services.scalewayai.api_key' => null]);
});

it('refuses a provider that is off, or one without its key', function (LlmProvider $provider): void {
    expect(fn (): LlmProviderInterface => app(LlmProviders::class)->forProvider($provider))->toThrow(LlmProviderUnavailableException::class);
})->with([
    'turned off' => fn (): LlmProvider => LlmProvider::factory()->disabled()->make(),
    'no key' => fn (): LlmProvider => LlmProvider::factory()->kind(LlmProviderKind::Scaleway)->make(),
]);
