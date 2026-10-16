<?php

use App\Admin\Settings\Actions\RemoveCustomLlmServerAction;
use App\Identity\Workspace\Models\User;
use Domain\Llm\Actions\Catalog\EnsureLlmProviderAction;
use Domain\Llm\Enums\LlmProviderKind;
use Domain\Llm\Models\Llm;
use Domain\Llm\Models\LlmProvider;

/**
 * Removing a server of your own (Settings → AI providers) turns it and its
 * models off and moves their agents to the replacement. A provider with an
 * account cannot be removed this way.
 */
it('removes a server of your own and its models, moving their agents', function (): void {
    $server = LlmProvider::factory()->create();
    $model = Llm::factory()->for($server, 'provider')->create();
    $other = testLlm('elsewhere');
    $agent = createAgent(User::factory()->create(), ['llm_id' => $model->id]);

    app(RemoveCustomLlmServerAction::class)->execute($server, $other);

    expect($server->fresh()->is_enabled)->toBeFalse()
        ->and($model->fresh()->is_enabled)->toBeFalse()
        ->and($agent->fresh()->llm_id)->toBe($other->id);
});

it('refuses to remove a provider with an account', function (): void {
    $groq = app(EnsureLlmProviderAction::class)->execute(LlmProviderKind::Groq);

    expect(function () use ($groq): void {
        app(RemoveCustomLlmServerAction::class)->execute($groq);
    })->toThrow(InvalidArgumentException::class, 'Only a server of your own can be removed here.');

    expect($groq->fresh()->is_enabled)->toBeTrue();
});
