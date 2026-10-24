<?php

declare(strict_types=1);

use Domain\Llm\Actions\Catalog\EnsureLlmProviderAction;
use Domain\Llm\Enums\LlmProviderKind;
use Domain\Llm\Models\LlmProvider;

/**
 * A provider with an account has one row, with no key: the key lives with
 * the account in Settings → AI providers.
 */
it('keeps one row per provider with an account, with no key', function (): void {
    $first = app(EnsureLlmProviderAction::class)->execute(LlmProviderKind::Groq);
    $again = app(EnsureLlmProviderAction::class)->execute(LlmProviderKind::Groq);

    expect($again->is($first))->toBeTrue()
        ->and($first->name)->toBe('Groq')
        ->and($first->api_key)->toBeNull();
});

it('refuses a server of your own, which has no account', function (): void {
    $before = LlmProvider::query()->count();

    expect(fn (): LlmProvider => app(EnsureLlmProviderAction::class)->execute(LlmProviderKind::Custom))->toThrow(InvalidArgumentException::class);

    expect(LlmProvider::query()->count())->toBe($before);
});
