<?php

use App\Admin\Settings\Actions\RemoveCustomLlmServerAction;
use App\Identity\Workspace\Models\User;
use Domain\Llm\Actions\Catalog\EnsureLlmProviderAction;
use Domain\Llm\Actions\Catalog\SaveCustomLlmServerAction;
use Domain\Llm\Data\Catalog\CustomLlmServerData;
use Domain\Llm\Enums\LlmProviderEnum;
use Domain\Llm\Models\Llm;
use Domain\Llm\Models\LlmProvider;

/**
 * A provider with an account has one row, with no key; a server of your own
 * keeps its address and key on its row, and removing it turns it and its
 * models off.
 */
it('keeps one row per provider with an account, with no key', function (): void {
    $first = app(EnsureLlmProviderAction::class)->execute(LlmProviderEnum::GROQ);
    $again = app(EnsureLlmProviderAction::class)->execute(LlmProviderEnum::GROQ);

    expect($again->is($first))->toBeTrue()
        ->and($first->name)->toBe('Groq')
        ->and($first->api_key)->toBeNull()
        ->and(fn () => app(EnsureLlmProviderAction::class)->execute(LlmProviderEnum::CUSTOM))->toThrow(InvalidArgumentException::class);
});

it('saves a server of your own, keeping its key when none is given', function (): void {
    $server = app(SaveCustomLlmServerAction::class)->execute(new CustomLlmServerData(' Office ', 'https://llm.test/v1/', 'key-1', ['X-Team' => 'a']));
    app(SaveCustomLlmServerAction::class)->execute(new CustomLlmServerData('Office', 'https://llm.test/v2'), $server);

    expect($server->fresh()->name)->toBe('Office')
        ->and($server->fresh()->base_url)->toBe('https://llm.test/v2')
        ->and($server->fresh()->api_key)->toBe('key-1')
        ->and($server->fresh()->headers)->toBeNull();
});

it('removes a server of your own and its models, moving their agents', function (): void {
    $server = LlmProvider::factory()->create();
    $model = Llm::factory()->for($server, 'provider')->create();
    $other = testLlm('elsewhere');
    $agent = createAgent(User::factory()->create(), ['llm_id' => $model->id]);

    app(RemoveCustomLlmServerAction::class)->execute($server, $other);

    expect($server->fresh()->is_enabled)->toBeFalse()
        ->and($model->fresh()->is_enabled)->toBeFalse()
        ->and($agent->fresh()->llm_id)->toBe($other->id)
        ->and(fn () => app(RemoveCustomLlmServerAction::class)->execute(app(EnsureLlmProviderAction::class)->execute(LlmProviderEnum::GROQ)))
        ->toThrow(InvalidArgumentException::class);
});
