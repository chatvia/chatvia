<?php

use Domain\Llm\Actions\Catalog\EnsureLlmProviderAction;
use Domain\Llm\Actions\Catalog\SaveCustomLlmServerAction;
use Domain\Llm\Data\Catalog\CustomLlmServerData;
use Domain\Llm\Enums\LlmProviderKind;
use Domain\Llm\Models\LlmProvider;

/**
 * A provider with an account has one row, with no key; a server of your own
 * keeps its address and key on its row.
 */
it('keeps one row per provider with an account, with no key', function (): void {
    $first = app(EnsureLlmProviderAction::class)->execute(LlmProviderKind::Groq);
    $again = app(EnsureLlmProviderAction::class)->execute(LlmProviderKind::Groq);

    expect($again->is($first))->toBeTrue()
        ->and($first->name)->toBe('Groq')
        ->and($first->api_key)->toBeNull()
        ->and(fn (): LlmProvider => app(EnsureLlmProviderAction::class)->execute(LlmProviderKind::Custom))->toThrow(InvalidArgumentException::class);
});

it('saves a server of your own, keeping its key when none is given', function (): void {
    $server = app(SaveCustomLlmServerAction::class)->execute(new CustomLlmServerData(' Office ', 'https://llm.test/v1/', 'key-1', ['X-Team' => 'a']));
    app(SaveCustomLlmServerAction::class)->execute(new CustomLlmServerData('Office', 'https://llm.test/v2'), $server);

    expect($server->fresh()->name)->toBe('Office')
        ->and($server->fresh()->base_url)->toBe('https://llm.test/v2')
        ->and($server->fresh()->api_key)->toBe('key-1')
        ->and($server->fresh()->headers)->toBeNull();
});
